<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Gateway;

use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\NullModelGateway;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullModelGatewayTest extends TestCase
{
    #[Test]
    public function itFailsClosedWhenNoProviderAdapterIsConfigured(): void
    {
        $gateway = new NullModelGateway();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/No AI provider adapter is configured/');

        $gateway->invoke(new ModelRequest(
            requestId: 'req-123',
            taskType: 'assistant_chat',
            promptVersion: 'assistant-chat.v1',
            outputSchemaVersion: 'ai.assistant.chat.v1',
            input: ['messages' => [['role' => 'user', 'content' => 'help']]],
            contextSources: ['resource:subject:list'],
        ));
    }
}
