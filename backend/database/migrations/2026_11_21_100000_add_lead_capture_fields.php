<?php

use Database\Seeders\LeadTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_types', function (Blueprint $table) {
            $table->json('keywords')->nullable();
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->string('file')->nullable()->change();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->json('headers')->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->text('source_text')->nullable();
        });

        foreach (LeadTypeSeeder::KEYWORDS as $slug => $keywords) {
            DB::table('lead_types')->where('slug', $slug)->whereNull('keywords')->update(['keywords' => json_encode($keywords)]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_id');
            $table->dropColumn('source_text');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_id');
            $table->dropColumn('headers');
        });

        Schema::table('lead_types', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
