<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table): void {
            if (! Schema::hasColumn('face_embeddings', 'overlay_image_path')) {
                $table->string('overlay_image_path')->nullable()->after('vector_image_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table): void {
            if (Schema::hasColumn('face_embeddings', 'overlay_image_path')) {
                $table->dropColumn('overlay_image_path');
            }
        });
    }
};

