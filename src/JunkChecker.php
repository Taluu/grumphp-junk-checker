<?php declare(strict_types=1);
namespace GrumPHPJunkChecker;

use GrumPHP\Runner\TaskResult;
use GrumPHP\Configuration\GrumPHP;

use GrumPHP\Task\TaskInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JunkChecker implements TaskInterface
{
    /** @var GrumPHP */
    private $grumPHP;

    public function __construct(GrumPHP $grumPHP)
    {
        $this->grumPHP = $grumPHP;
    }

    public function getName()
    {
        return 'junk_checker';
    }

    public function getConfiguration()
    {
        $configured = $this->grumPHP->getTaskConfiguration($this->getName());
        return $this->getConfigurableOptions()->resolve($configured);
    }

    public function getConfigurableOptions()
    {
        $resolver = new OptionsResolver;

        $resolver->setDefault('triggered_by', ['php']);
        $resolver->setDefault('junks', []);

        $resolver->setAllowedTypes('triggered_by', ['string[]']);
        $resolver->setAllowedTypes('junks', ['string[]']);

        return $resolver;
    }

    public function canRunInContext(ContextInterface $context)
    {
        return $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context)
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

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $path = $file->getPathname();
            $content = $file->getContents();

            foreach ($config['junks'] as $junk) {
                if (strpos($content, $junk) === false) {
                    continue;
                }

                $errors[] = "- Junk {$junk} detected in {$path}.";
            }
        }

        if (count($errors) === 0) {
            return TaskResult::createPassed($this, $context);
        }

        return TaskResult::createFailed($this, $context, implode("\n", $errors));
    }
}
