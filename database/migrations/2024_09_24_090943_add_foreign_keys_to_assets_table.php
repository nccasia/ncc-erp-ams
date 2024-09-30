<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToAssetsTable extends Migration
{
        public function up()
        {
            Schema::table('assets', function (Blueprint $table) {
                // Thêm cột khách hàng và dự án, và tạo khóa ngoại
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
    
                // Tạo khóa ngoại tham chiếu đến bảng customers và projects
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            });
        }
    
        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::table('assets', function (Blueprint $table) {
                // Xóa khóa ngoại và cột
                $table->dropForeign(['customer_id']);
                $table->dropForeign(['project_id']);
                $table->dropColumn(['customer_id', 'project_id']);
            });
        }
}
