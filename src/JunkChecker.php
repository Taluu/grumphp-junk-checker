<?php

declare(strict_types=1);

namespace GrumPHPJunkChecker;

use GrumPHP\Runner\TaskResult;
use GrumPHP\Configuration\GrumPHP;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Config\EmptyTaskConfig;
use GrumPHP\Task\TaskInterface;
use GrumPHP\Task\Config\TaskConfigInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function count;
use function in_array;

final class JunkChecker implements TaskInterface
{
    private const T_STRING = T_STRING;
    private const T_FUNCTION = T_FUNCTION;
    private const T_DOUBLE_COLON = T_DOUBLE_COLON;
    private const T_OBJECT_OPERATOR = T_OBJECT_OPERATOR;
    private const T_OPEN_PARENTHESIS = 20000;

    /** @var TaskConfigInterface */
    private $config;

    public function __construct()
    {
        $this->config = new EmptyTaskConfig();
    }

    public function getName(): string
    {
        return 'junk_checker';
    }

    public function getConfig(): TaskConfigInterface
    {
        return $this->config;
    }

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        $resolver->setDefault('triggered_by', ['php']);
        $resolver->setDefault('junks', []);

        $resolver->setAllowedTypes('triggered_by', ['string[]']);
        $resolver->setAllowedTypes('junks', ['string[]']);

        return $resolver;
    }

    public function withConfig(TaskConfigInterface $config): TaskInterface
    {
        $new = clone $this;
        $new->config = $config;

        return $new;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        /** @var array{triggered_by: list<string>, junks: list<string>} $config */
        $config = $this->config->getOptions();
        $files = $context->getFiles()->extensions($config['triggered_by']);

        if (count($files) === 0) {
            return TaskResult::createSkipped($this, $context);
        }

        if (count($config['junks']) === 0) {
            return TaskResult::createSkipped($this, $context);
        }

        $errors = [];

        $whitelist = [
            self::T_STRING,
            self::T_FUNCTION,
            self::T_DOUBLE_COLON,
            self::T_OBJECT_OPERATOR,
        ];

        $filter =
            /** @psalm-param string|array{0:int, 1:string, 2:int} $token */
            static function ($token) use ($whitelist): bool {
                if (!is_array($token)) {
                    return $token === '(';
                }

                return in_array($token[0], $whitelist, true);
            };

        $map =
            /**
             * @psalm-param string|array{0:int, 1:string, 2:int} $token
             * @psalm-return array{0:int, 1:string, 2:?int}
             */
            static function ($token) {
                assert(is_array($token) || $token === '(');

                return $token === '('
                    ? [self::T_OPEN_PARENTHESIS, $token, null]
                    : $token
                ;
            };

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $path = $file->getPathname();
            $content = $file->getContents();

            $tokens = array_map(
                $map,
                array_values(
                    array_filter(
                        token_get_all($content),
                        $filter
                    )
                )
            );

            foreach ($tokens as $key => [$token, $value, $line]) {
                if ($token !== self::T_STRING) {
                    continue;
                }

                if (!in_array($value, $config['junks'], true)) {
                    continue;
                }

                if (!isset($tokens[$key + 1])) {
                    continue;
                }

                if ($tokens[$key + 1][0] !== self::T_OPEN_PARENTHESIS) {
                    continue;
                }

                if (isset($tokens[$key - 1])) {
                    // is it a declaration ? If it is, no interests...
                    if ($tokens[$key - 1][0] === self::T_FUNCTION) {
                        continue;
                    }

                    // is it a method call ? We can allow method call...
                    if ($tokens[$key - 1][0] === self::T_OBJECT_OPERATOR) {
                        continue;
                    }

                    if ($tokens[$key - 1][0] === self::T_DOUBLE_COLON) {
                        continue;
                    }
                }

                $errors[] = "- Junk detected in {$path}, line {$line}.";
            }
        }

        if (count($errors) === 0) {
            return TaskResult::createPassed($this, $context);
        }

        return TaskResult::createFailed($this, $context, implode("\n", $errors));
    }
}
