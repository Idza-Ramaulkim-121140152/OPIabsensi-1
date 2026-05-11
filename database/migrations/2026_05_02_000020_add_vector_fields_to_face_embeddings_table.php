<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->unsignedInteger('landmark_count')->default(0)->after('landmarks');
            $table->string('vector_image_path')->nullable()->after('image_height');
        });
    }

    public function down(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->dropColumn(['landmark_count', 'vector_image_path']);
        });
    }
};
