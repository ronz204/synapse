<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archivable_type', 120);
            $table->unsignedBigInteger('archivable_id');
            $table->string('tipo_documento', 60);
            $table->string('nombre_original');
            $table->string('disco', 30)->default('local');
            $table->string('ruta');
            $table->string('mime_type', 100);
            $table->unsignedInteger('tamano_bytes');
            $table->char('hash_sha256', 64);
            $table->timestamps();

            $table->unique(['disco', 'ruta'], 'archivos_disco_ruta_unique');
            $table->index(['archivable_type', 'archivable_id'], 'archivos_archivable_index');
            $table->index('tipo_documento', 'archivos_tipo_documento_index');
        });

        DB::statement('ALTER TABLE archivos ADD CONSTRAINT chk_archivos_tamano CHECK (tamano_bytes > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};
