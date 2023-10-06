<?php namespace Pfm\Ministry\Updates;

use Db;
use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * UpdateRainlabUsersTable2 Migration
 */
class UpdateRainlabUsersTable2 extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('is_cognito_user_existing')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_cognito_user_existing');
        });
    }
}
