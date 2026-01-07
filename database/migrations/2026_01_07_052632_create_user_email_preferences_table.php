<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_email_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('weekly_reports')->default(true);
            $table->boolean('monthly_reports')->default(true);
            $table->string('weekly_day')->default('monday'); // day of week
            $table->integer('monthly_day')->default(1); // day of month (1-28)
            $table->time('preferred_time')->default('08:00:00');
            $table->boolean('include_pdf')->default(true);
            $table->boolean('include_charts')->default(true);
            $table->json('custom_date_ranges')->nullable(); // for custom reports
            $table->timestamp('last_weekly_sent')->nullable();
            $table->timestamp('last_monthly_sent')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_email_preferences');
    }
};
