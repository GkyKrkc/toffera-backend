<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('purpose');
            $table->string('message');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed', 'stub_logged'])->default('queued');
            $table->decimal('cost', 8, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_dispatch_logs');
    }
};
