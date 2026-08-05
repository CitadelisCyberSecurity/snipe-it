<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opt-in, not opt-out: launching a campaign emails every manager it
        // snapshotted, so the default has to be the quiet one. A campaign created
        // to try the feature out must not mail the organisation because whoever
        // made it did not know there was a box to untick.
        //
        // Existing campaigns inherit false. Draft ones can be ticked before launch;
        // already-active ones have sent whatever they were going to send.
        Schema::table('access_review_campaigns', function (Blueprint $table) {
            $table->boolean('notify_managers_on_launch')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('access_review_campaigns', function (Blueprint $table) {
            $table->dropColumn('notify_managers_on_launch');
        });
    }
};
