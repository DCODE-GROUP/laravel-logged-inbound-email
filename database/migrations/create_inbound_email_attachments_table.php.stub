<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_email_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inbound_email_id')
                ->constrained('inbound_emails')
                ->cascadeOnDelete();

            $table->string('filename');
            $table->string('disk');
            $table->string('path');
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_email_attachments');
    }
};
