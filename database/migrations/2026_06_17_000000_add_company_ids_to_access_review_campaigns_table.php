<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_review_campaigns', function (Blueprint $table) {
            $table->json('company_ids')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('access_review_campaigns', function (Blueprint $table) {
            $table->dropColumn('company_ids');
        });
    }
};
