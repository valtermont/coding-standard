<?php declare(strict_types=1);

namespace Symplify\CodingStandard\Fixer\Naming;

use Nette\Utils\Strings;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\Fixer\DefinedFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Symplify\TokenRunner\Wrapper\FixerWrapper\ClassWrapper;
use Symplify\TokenRunner\Wrapper\FixerWrapper\ClassWrapperFactory;

final class ClassNameSuffixByParentFixer implements DefinedFixerInterface, ConfigurationDefinitionFixerInterface
{
    /**
     * @var string
     */
    private const PARENT_TYPES_TO_SUFFIXES_OPTION = 'parent_types_to_suffixes';

    /**
     * @var string
     */
    private const EXTRA_PARENT_TYPES_TO_SUFFIXES_OPTION = 'extra_parent_types_to_suffixes';

    /**
     * @var string[]
     */
    private $defaultParentClassToSuffixMap = [
        '*Command',
        '*Controller',
        '*Repository',
        '*Presenter',
        '*Request',
        '*Response',
        '*EventSubscriber',
        '*FixerInterface',
        '*Sniff',
        '*Exception',
        '*Handler',
    ];

    /**
     * @var mixed[]
     */
    private $configuration = [];

    /**
     * @var ClassWrapperFactory
     */
    private $classWrapperFactory;

    public function __construct(ClassWrapperFactory $classWrapperFactory)
    {
        $this->classWrapperFactory = $classWrapperFactory;

        // set defaults
        $this->configuration = $this->getConfigurationDefinition()
            ->resolve([]);
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition('Class should have suffix by parent class/interface', [
            new CodeSample(
                <<<CODE
<?php

class SomeClass extends Command
{
}
CODE
            ),
        ]);
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAllTokenKindsFound([T_STRING, T_CLASS])
            && $tokens->isAnyTokenKindsFound([T_EXTENDS, T_IMPLEMENTS]);
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = $tokens->count() - 1; $index >= 0; --$index) {
            $token = $tokens[$index];

            if (! $token->isClassy()) {
                continue;
            }

            $classWrapper = $this->classWrapperFactory->createFromTokensArrayStartPosition($tokens, $index);
            $this->processClassWrapper($tokens, $classWrapper);
        }
    }

    public function isRisky(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return self::class;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * @param mixed[]|null $configuration
     */
    public function configure(?array $configuration = null): void
    {
        if ($configuration === null) {
            return;
        }

        $this->configuration = $this->getConfigurationDefinition()
            ->resolve($configuration);
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        $fixerOptionBuilder = new FixerOptionBuilder(
            self::PARENT_TYPES_TO_SUFFIXES_OPTION,
            'Map of parent classes to suffixes, that their children should have'
        );

        $parentTypesToSuffixesOption = $fixerOptionBuilder->setAllowedTypes(['array'])
            ->setDefault($this->defaultParentClassToSuffixMap)
            ->getOption();

        $fixerOptionBuilder = new FixerOptionBuilder(
            self::EXTRA_PARENT_TYPES_TO_SUFFIXES_OPTION,
            'Extra map of parent classes to suffixes, that their children should have'
        );

        $extraParentTypesToSuffixesOption = $fixerOptionBuilder->setAllowedTypes(['array'])
            ->setDefault([])
            ->getOption();

        return new FixerConfigurationResolver([$parentTypesToSuffixesOption, $extraParentTypesToSuffixesOption]);
    }

    private function processClassWrapper(Tokens $tokens, ClassWrapper $classWrapper): void
    {
        $className = $classWrapper->getName();
        if ($className === null) {
            return;
        }

        $parentClassName = $classWrapper->getParentClassName();

        if ($parentClassName) {
            $this->processType($tokens, $classWrapper, $parentClassName, $className);
        }

        foreach ($classWrapper->getInterfaceNames() as $interfaceName) {
            $this->processType($tokens, $classWrapper, $interfaceName, $className);
        }
    }

    private function processType(
        Tokens $tokens,
        ClassWrapper $classWrapper,
        string $parentType,
        string $className
    ): void {
        $classToSuffixMap = $this->getClassToSuffixMap();

        foreach ($classToSuffixMap as $classMatch => $suffix) {
            if (! fnmatch($classMatch, $parentType) && ! fnmatch($classMatch . 'Interface', $parentType)) {
                continue;
            }

            if (Strings::endsWith($className, $suffix)) {
                continue;
            }

            $tokens[$classWrapper->getNamePosition()] = new Token([T_STRING, $className . $suffix]);
        }
    }

    /**
     * @return string[]
     */
    private function getClassToSuffixMap(): array
    {
        $parentTypesToSuffixes = $this->configuration[self::PARENT_TYPES_TO_SUFFIXES_OPTION];
        $extraParentTypesToSuffixes = $this->configuration[self::EXTRA_PARENT_TYPES_TO_SUFFIXES_OPTION];

        $typesToSuffixes = array_merge($parentTypesToSuffixes, $extraParentTypesToSuffixes);

        // allow 1 option with numeric keys
        foreach ($typesToSuffixes as $key => $type) {
            if (! is_numeric($key)) {
                continue;
            }

            $suffix = ltrim($type, '*');

            // remove "Interface" suffix
            if (Strings::endsWith($suffix, 'Interface')) {
                $suffix = substr($suffix, 0, -strlen('Interface'));
            }

            $typesToSuffixes[$type] = $suffix;

            unset($typesToSuffixes[$key]);
        }

        return $typesToSuffixes;
    }
}
