<?php

namespace Tests\Unit;

use App\Support\Builder360Icon;
use PHPUnit\Framework\TestCase;

class Builder360IconTest extends TestCase
{
    public function test_known_builder360_icons_map_to_local_font_awesome_classes(): void
    {
        $this->assertSame('fa-table-cells-large', Builder360Icon::classFor('grid'));
        $this->assertSame('fa-list-check', Builder360Icon::classFor('tasks'));
        $this->assertSame('fa-comment', Builder360Icon::classFor('bubble'));
        $this->assertSame('fa-indian-rupee-sign', Builder360Icon::classFor('rupee'));
    }

    public function test_unknown_icon_fails_to_a_safe_neutral_glyph(): void
    {
        $this->assertSame('fa-circle', Builder360Icon::classFor('unknown'));
        $this->assertSame('fa-circle', Builder360Icon::classFor(null));
    }
}
