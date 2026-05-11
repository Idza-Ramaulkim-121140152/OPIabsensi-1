<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->json('landmarks')->nullable()->after('embedding');
            $table->unsignedInteger('image_width')->nullable()->after('landmarks');
            $table->unsignedInteger('image_height')->nullable()->after('image_width');
        });
    }

    public function down(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->dropColumn(['landmarks', 'image_width', 'image_height']);
        });
    }
};
