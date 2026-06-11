<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm fields face verification vào bảng attendances.
     * - face_verified: kết quả xác thực (true/false)
     * - face_confidence: confidence score (1 - distance/threshold)
     * - face_distance: euclidean distance giữa 2 face encodings
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('face_verified')->nullable()->after('check_out_image_path');
            $table->float('face_confidence', 5, 4)->nullable()->after('face_verified');
            $table->float('face_distance', 5, 4)->nullable()->after('face_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['face_verified', 'face_confidence', 'face_distance']);
        });
    }
};
