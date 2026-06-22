<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_review_items', function (Blueprint $table) {
            $table->id();
            $table->integer('campaign_id');
            $table->integer('user_id');
            $table->integer('manager_id');
            $table->integer('license_id');
            $table->integer('license_seat_id');
            $table->string('license_name_snapshot');
            $table->decimal('cost_per_seat_snapshot', 20, 2)->nullable();
            $table->string('manager_status', 16)->nullable();
            $table->text('manager_comment')->nullable();
            $table->timestamp('manager_completed_at')->nullable();
            $table->timestamp('admin_executed_at')->nullable();
            $table->integer('admin_executed_by')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('manager_id');
            $table->index(['campaign_id', 'manager_id']);
            $table->index('user_id');
            $table->index('license_seat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_items');
    }
};
