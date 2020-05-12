<?php

declare(strict_types=1);

namespace GrumPHPJunkChecker;

use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\TaskInterface;
use GrumPHP\Test\Task\AbstractTaskTestCase;

class JunkCheckerTest extends AbstractTaskTestCase
{
    /** @var vfsStreamDirectory */
    private static $root;

    protected function provideTask(): TaskInterface
    {
        return new JunkChecker();
    }

    public function provideConfigurableOptions(): iterable
    {
        yield 'defaults' => [
            [],
            [
                'triggered_by' => ['php'],
                'junks' => [],
            ]
        ];

        yield 'with junks' => [
            [
                'junks' => ['var_dump', 'dump'],
            ],
            [
                'triggered_by' => ['php'],
                'junks' => ['var_dump', 'dump'],
            ]
        ];
    }

    public function provideRunContexts(): iterable
    {
        yield 'allows git pre commit context' => [
            true,
            $this->mockContext(GitPreCommitContext::class),
        ];

        yield 'disallow anything else' => [
            false,
            $this->mockContext(ContextInterface::class),
        ];
    }

    public function provideFailsOnStuff(): iterable
    {
        yield 'it detects a junk in the file' => [
            [
                'junks' => ['var_dump'],
            ],
            $this->mockContext(GitPreCommitContext::class, [__DIR__ . '/../fixtures/fail/method-detected.php']),
            function (): void {
            },
            'Junk detected',
        ];
    }

    public function providePassesOnStuff(): iterable
    {
        yield 'it doesn\'t detect any junks in the file' => [
            [
                'junks' => ['var_dump'],
            ],
            $this->mockContext(GitPreCommitContext::class, [__DIR__ . '/../fixtures/success/no-calls.php']),
            function (): void {
            },
        ];

        yield 'it allows when a static method has the same name as the junk' => [
            [
                'junks' => ['var_dump'],
            ],
            $this->mockContext(GitPreCommitContext::class, [__DIR__ . '/../fixtures/success/static-method-call.php']),
            function (): void {
            },
        ];

        yield 'it allows when a method has the same name as the junk' => [
            [
                'junks' => ['var_dump'],
            ],
            $this->mockContext(GitPreCommitContext::class, [__DIR__ . '/../fixtures/success/method-call.php']),
            function (): void {
            },
        ];

        yield 'it allows a junk call if it\'s commented' => [
            [
                'junks' => ['var_dump'],
            ],
            $this->mockContext(GitPreCommitContext::class, [__DIR__ . '/../fixtures/success/commented-call.php']),
            function (): void {
            },
        ];
    }

    public function provideSkipsOnStuff(): iterable
    {
        yield 'it skips on non supported file extensions' => [
            [
                'junks' => ['var_dump'],
            ],
            $this->mockContext(GitPreCommitContext::class, [
                'foo-bar.yaml',
                'foo-bar.xml',
                'foo-bar.other',
            ]),
            function (): void {
            },
        ];

        yield 'it skips on empty junk config' => [
            [
                'junks' => [],
            ],
            $this->mockContext(GitPreCommitContext::class, [
                'foo-bar.php',
            ]),
            function (): void {
            },
        ];
    }
}
