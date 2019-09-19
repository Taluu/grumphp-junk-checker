<?php declare(strict_types=1);
namespace GrumPHPJunkChecker;

use GrumPHP\Runner\TaskResult;
use GrumPHP\Configuration\GrumPHP;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\TaskInterface;
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

    /** @var GrumPHP */
    private $grumPHP;

    public function __construct(GrumPHP $grumPHP)
    {
        $this->grumPHP = $grumPHP;
    }

    public function getName(): string
    {
        return 'junk_checker';
    }

    public function getConfiguration(): array
    {
        $configured = $this->grumPHP->getTaskConfiguration($this->getName());
        return $this->getConfigurableOptions()->resolve($configured);
    }

    public function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver;

        $resolver->setDefault('triggered_by', ['php']);
        $resolver->setDefault('junks', []);

        $resolver->setAllowedTypes('triggered_by', ['string[]']);
        $resolver->setAllowedTypes('junks', ['string[]']);

        return $resolver;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfiguration();
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

        $filter = function ($token) use ($whitelist) {
            if (!is_array($token)) {
                return $token === '(';
            }

            return in_array($token[0], $whitelist, true);
        };

        $map = function ($token) {
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

                if (!in_array($value, $config['junks'])) {
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
