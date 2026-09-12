import { amazonInParser } from './amazon-in/index.js';
import { flipkartParser } from './flipkart/index.js';
import { assertImplementsParserInterface } from './shared/parserInterface.js';

assertImplementsParserInterface(amazonInParser);
assertImplementsParserInterface(flipkartParser);

const PARSERS = {
    amazon_in: amazonInParser,
    flipkart: flipkartParser,
};

export function parserFor(provider) {
    const parser = PARSERS[provider];
    if (!parser) throw new Error(`No parser registered for provider "${provider}".`);
    return parser;
}
