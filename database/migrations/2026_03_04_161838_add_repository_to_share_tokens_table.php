<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('share_tokens', function (Blueprint $table) {
            $table->string('repository')->nullable()->after('connection');
        });
    }

    public function down(): void
    {
        Schema::table('share_tokens', function (Blueprint $table) {
            $table->dropColumn('repository');
        });
    }
};
