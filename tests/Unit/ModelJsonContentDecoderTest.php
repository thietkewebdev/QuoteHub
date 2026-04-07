<?php

namespace Tests\Unit;

use App\Services\AI\Support\ModelJsonContentDecoder;
use PHPUnit\Framework\TestCase;

class ModelJsonContentDecoderTest extends TestCase
{
    public function test_decodes_plain_json_object(): void
    {
        $out = ModelJsonContentDecoder::decodeObject('{"a":1,"b":"x"}');

        $this->assertSame(['a' => 1, 'b' => 'x'], $out);
    }

    public function test_strips_json_markdown_fence(): void
    {
        $out = ModelJsonContentDecoder::decodeObject(<<<'TXT'
```json
{"items":[]}
```
TXT);

        $this->assertSame(['items' => []], $out);
    }
}
