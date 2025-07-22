<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void {
Schema::create('product_variants', function (Blueprint $table) {
$table->id();
$table->unsignedBigInteger('product_id');
$table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
$table->string('color');
$table->string('size');
$table->integer('stock')->default(0);
$table->timestamps();
});
}

public function down(): void {
Schema::dropIfExists('product_variants');
}
};
