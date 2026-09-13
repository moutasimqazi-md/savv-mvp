import { amazonInParser } from './amazon-in/index.js';
import { walmartParser } from './walmart/index.js';
import { claudeParser } from './claude/index.js';
import { assertImplementsParserInterface } from './shared/parserInterface.js';

assertImplementsParserInterface(amazonInParser);
assertImplementsParserInterface(walmartParser);

const PARSERS = {
    amazon_in: amazonInParser,
    walmart: walmartParser,
};

const SUBSCRIPTION_PARSERS = {
    claude: claudeParser,
};

/** Which "kind" of parser a provider uses - keep in sync with Savv\Enums\ProviderKind. */
const PROVIDER_KIND = {
    amazon_in: 'orders',
    walmart: 'orders',
    claude: 'subscription',
};

export function parserFor(provider) {
    const parser = PARSERS[provider] ?? SUBSCRIPTION_PARSERS[provider];
    if (!parser) throw new Error(`No parser registered for provider "${provider}".`);
    return parser;
}

export function kindFor(provider) {
    const kind = PROVIDER_KIND[provider];
    if (!kind) throw new Error(`No provider kind registered for "${provider}".`);
    return kind;
}

export function isKnownProvider(provider) {
    return Object.prototype.hasOwnProperty.call(PROVIDER_KIND, provider);
}
