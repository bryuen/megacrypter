<?php

/**
 * Provides gettext-based i18n support for Twig 3.x templates.
 *
 * Replaces the deprecated twig/extensions I18n extension.
 * Supports:
 *   - {% trans "string" %} tag
 *   - {{ variable|trans }} filter
 */

use Twig\Compiler;
use Twig\Extension\AbstractExtension;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFilter;

class TwigTransNode extends Node
{
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);
        $compiler->write('echo gettext(');
        $compiler->subcompile($this->getNode('body'));
        $compiler->raw(");\n");
    }
}

class TwigTransTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $body = $this->parser->getExpressionParser()->parseExpression();
        $stream->expect(Token::BLOCK_END_TYPE);

        return new TwigTransNode(['body' => $body], [], $token->getLine());
    }

    public function getTag(): string
    {
        return 'trans';
    }
}

class TwigI18nExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', 'gettext'),
        ];
    }

    public function getTokenParsers(): array
    {
        return [new TwigTransTokenParser()];
    }
}
