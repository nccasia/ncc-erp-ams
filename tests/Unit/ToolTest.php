<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolUser;
use App\Models\User;

class ToolTest extends BaseTest
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testAToolBelongsToASupplier()
    {
        $tool = Tool::factory()
        ->create(
            [
                'supplier_id' => Supplier::factory()->create()->id,
                'status_id' => Statuslabel::factory()->create()->id,
                'category_id' => Category::factory()->create([
                    'category_type' => 'tool'
                ])->id,
            ]
        );
        $this->assertInstanceOf(Supplier::class, $tool->supplier);
    }

    public function testAToolBelongsToCategory()
    {
        $tool = Tool::factory()
        ->create(
            [
                'supplier_id' => Supplier::factory()->create()->id,
                'status_id' => Statuslabel::factory()->create()->id,
                'category_id' => Category::factory()->create([
                    'category_type' => 'tool'
                ])->id,
            ]
        );
        $this->assertInstanceOf(Category::class, $tool->category);
    }
}
