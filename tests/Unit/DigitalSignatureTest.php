<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\DigitalSignatures;
use App\Models\Location;
use App\Models\Supplier;
use Tests\Unit\BaseTest;

class DigitalSignatureTest extends BaseTest
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testADigitalSignatureBelongToASupplier()
    {
        $supplier = Supplier::factory()->create();
        $digital_signature = DigitalSignatures::factory()->create(
            [
                'supplier_id' => $supplier->id
            ]
        );
        $this->assertInstanceOf(Supplier::class,$digital_signature->supplier);
    }

    public function testADigitalSignatureHasALocation()
    {
        $location = Location::factory()->create();
        $supplier = Supplier::factory()->create();
        $digital_signature = DigitalSignatures::factory()->create(
            [
                'location_id' => $location->id,
                'supplier_id' => $supplier->id
            ]
        );
        $this->assertInstanceOf(Location::class,$digital_signature->location);
    }

    public function testADigitalSignatureBelongToACategory()
    {
        $category = Category::factory()->create(['category_type' => 'taxtoken']);
        $supplier = Supplier::factory()->create();
        $digital_signature = DigitalSignatures::factory()->create(
            [
                'category_id' => $category->id,
                'supplier_id' => $supplier->id
            ]
        );
        $this->assertInstanceOf(Category::class, $digital_signature->category);
        $this->assertEquals('taxtoken', $digital_signature->category->category_type);
    }

}
