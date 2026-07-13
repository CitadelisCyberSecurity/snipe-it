<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_review_items', function (Blueprint $table) {
            // Distinguishes seats auto-executed when a manager marks their review complete
            // (a "keep" that needs no admin action) from seats an admin explicitly executed.
            // Kept separate for audit clarity / separation of duties in SOC 2 reporting.
            $table->boolean('auto_executed')->default(false)->after('admin_executed_by');
        });
    }

    public function down(): void
    {
        Schema::table('access_review_items', function (Blueprint $table) {
            $table->dropColumn('auto_executed');
        });
    }
};
