<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_type_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->string('field_type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['lead_type_id', 'key']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained();
            $table->foreignId('lead_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->string('reply_status', 20)->nullable()->index();
            $table->timestamp('reply_sent_at')->nullable();
            $table->foreignId('reply_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->json('custom_data')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_name')->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('status', 20)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'sent_at']);
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10)->default('note');
            $table->text('body')->nullable();
            $table->string('call_outcome')->nullable();
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->boolean('auto')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_type_fields');
        Schema::dropIfExists('lead_types');
        Schema::dropIfExists('contacts');
    }
};
