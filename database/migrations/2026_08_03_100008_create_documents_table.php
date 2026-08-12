<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            $table->string('document_type', 60);
            $table->string('original_name');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->char('hash_sha256', 64);
            $table->timestamps();

            $table->unique(['disk', 'path'], 'documents_disk_path_unique');
            $table->index(['documentable_type', 'documentable_id'], 'documents_documentable_index');
            $table->index('document_type', 'documents_document_type_index');
        });

        DB::statement('ALTER TABLE documents ADD CONSTRAINT chk_documents_size CHECK (size_bytes > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
