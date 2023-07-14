<?php
namespace Tests\Unit;

use App\Models\Statuslabel;
use Tests\Unit\BaseTest;

class StatuslabelTest extends BaseTest
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testPendingStatuslabelAdd()
    {
        $statuslabel = Statuslabel::factory()->pending()->create();
        $this->assertModelExists($statuslabel);
    }

    public function testBrokenStatuslabelAdd()
    {
        $statuslabel = Statuslabel::factory()->pending()->create();
        $this->assertModelExists($statuslabel);
    }

    public function testAssignStatuslabelAdd()
    {
        $statuslabel = Statuslabel::factory()->pending()->create();
        $this->assertModelExists($statuslabel);
    }

    public function testRTDStatuslabelAdd()
    {
        $this->withoutExceptionHandling();
        $statuslabel = Statuslabel::factory()->readyToDeploy()->create();
        $this->assertModelExists($statuslabel);
    }
}
