<?php
namespace Tests\Unit;

use App\Models\Location;
use Tests\Unit\BaseTest;


class LocationTest extends BaseTest
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testPassesIfNotSelfParent()
    {
        $this->createValidLocation(['id' => 999]);

        $a = Location::factory()->make([
            'name' => 'Test Location',
            'parent_id' => 999,
        ]);

        $this->assertTrue($a->isValid());
    }

    public function testFailsIfSelfParent()
    {
        $a = Location::factory()->make([
            'name' => 'Test Location',
            'id' => 999,
            'parent_id' => 999,
        ]);

        $this->assertFalse($a->isValid());
        $this->assertStringContainsString(trans('validation.non_circular', ['attribute' => 'parent id']), $a->getErrors());
    }
}
