<?php
//
//use Illuminate\Database\Migrations\Migration;
//use Illuminate\Database\Schema\Blueprint;
//use Illuminate\Support\Facades\Schema;
//
//return new class extends Migration
//{
//    /**
//     * Run the migrations.
//     */
//    public function up()
//    {
//        Schema::create('products', function (Blueprint $table) {
//            $table->id();
//            $table->string('name');
//            $table->text('description')->nullable();
//            $table->decimal('price', 10, 2);
//            $table->unsignedBigInteger('category_id');
//            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
//            $table->integer('stock')->default(0);
//            $table->string('size'); // S, M, L, XL
//            $table->string('team'); // Brazil, Argentina etc
//            $table->string('color'); // Yellow, Blue etc
//            $table->enum('variant', ['home', 'away', 'special', 'other']);
//            $table->string('image')->nullable(); // image path
//            $table->timestamps();
//        });
//    }
//
//    /**
//     * Reverse the migrations.
//     */
//    public function down(): void
//    {
//        Schema::dropIfExists('products');
//    }
//};


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedBigInteger('category_id');
            $table->string('team');
            $table->string('image')->nullable();
            $table->string('role');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
