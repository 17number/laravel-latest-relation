<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EloquentLatestRelationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('logins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('created_at');
            $table->unsignedBigInteger('user_id');
            $table->enum('device_type', ['mobile', 'desktop']);
            $table->string('profession')->nullable();
        });

        DB::table('users')->insert([
            [
                'name' => 'Ferris Bueller',
                'email' => 'ferris@buellerandco.com'
            ], [
                'name' => 'Cameron Frye',
                'email' => 'cameron@nervouswrecks.com'
            ], [
                'name' => 'Ed Rooney',
                'email' => 'ed@rooneypi.com'
            ],
        ]);

        DB::table('logins')->insert([
            [
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(3),
                'device_type' => 'mobile',
                'profession' => null
            ], [
 
                'user_id' => 2,
                'created_at' => Carbon::now()->subDays(3),
                'device_type' => 'mobile',
                'profession' => null
            ], [
                'user_id' => 3,
                'created_at' => Carbon::now()->subDays(3),
                'device_type' => 'mobile',
                'profession' => null],
            
            [
                'user_id' => 1,
                'created_at' => Carbon::now()->subDay(2),
                'device_type' => 'desktop',
                'profession' => null
            ], [
                'user_id' => 2,
                'created_at' => Carbon::now()->subDay(2),
                'device_type' => 'desktop',
                'profession' => null
            ], [
                'user_id' => 3,
                'created_at' => Carbon::now()->subDay(2),
                'device_type' => 'desktop',
                'profession' => null
            ], [
                'user_id' => 1,
                'created_at' => Carbon::now(),
                'device_type' => 'desktop',
                'profession' => 'Leisure Consultant'
            ], [
                'user_id' => 2,
                'created_at' => Carbon::now()->subDay(1),
                'device_type' => 'mobile',
                'profession' => null
            ], [
                'user_id' => 3,
                'created_at' => Carbon::now(),
                'device_type' => 'mobile',
                'profession' => null
            ],
        ]);
    }

    /**
     * @test
     */
    public function latest_relation()
    {
        $professions = User::whereHas('logins', function ($query) {
            $query->latestRelation()->whereNotNull('profession');
        });

        $this->assertSame(1, $professions->count());
        $this->assertSame('Ferris Bueller', $professions->first()->name);

        $loggedInYesterday = User::whereHas('logins', function ($query) {
            $query->latestRelation()->whereBetween(
                'created_at', [
                    Carbon::now()->subDay(1)->startOfDay(),
                    Carbon::now()->subDay(1)->endOfDay()
                ]);
        });

        $this->assertSame(1, $loggedInYesterday->count());
        $this->assertSame('Cameron Frye', $loggedInYesterday->first()->name);
    }
    
    /**
     * @test
     */
    public function where_latest()
    {
        $users = User::whereHas('logins', function ($query) {
            $query->whereLatest('device_type', 'mobile');
        })->get();

        $this->assertCount(2, $users);
        $this->assertSame('Cameron Frye', $users->first()->name);
        $this->assertSame('mobile', $users->first()->lastLogin->device_type);
        $this->assertTrue($users->first()->lastLogin->created_at->isYesterday());
        $this->assertSame('Ed Rooney', $users->last()->name);
        $this->assertSame('mobile', $users->last()->lastLogin->device_type);
        $this->assertTrue($users->last()->lastLogin->created_at->isToday());

        $users = User::whereHas('logins', function ($query) {
            $query->whereLatest('device_type', 'desktop');
        })->get();

        $this->assertCount(1, $users);
        $this->assertSame('Ferris Bueller', $users->first()->name);
        $this->assertSame('desktop', $users->first()->lastLogin->device_type);
    }
}

class User extends Model
{
    public function logins()
    {
        return $this->hasMany(Login::class);
    }

    public function lastLogin()
    {
        return $this->hasOne(Login::class)->latest();
    }
}

class Login extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}