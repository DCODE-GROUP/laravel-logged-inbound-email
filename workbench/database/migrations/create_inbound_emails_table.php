<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();

            $table->longText('payload');

            $table->string('provider');
            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();
            $table->string('subject')->nullable();
            $table->longText('text_content')->nullable();
            $table->longText('html_content')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->string('status');
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
    }
};
