<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('provider_conversation_id')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->change();
            $table->boolean('is_agency')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['is_agency']);
            $table->dropColumn('is_agency');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('provider_conversation_id');
        });
    }
};
