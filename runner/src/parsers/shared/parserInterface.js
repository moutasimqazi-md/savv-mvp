/**
 * Every provider parser (amazon-us, walmart, claude) must implement this shape.
 * `page` is a Playwright Page already navigated to a supported order page.
 *
 * @typedef {object} SavvParser
 * @property {(page: import('playwright').Page) => Promise<boolean>} supportsCurrentPage
 * @property {(page: import('playwright').Page) => Promise<string>} detectPageType
 * @property {(page: import('playwright').Page) => Promise<object[]>} extractOrders
 * @property {(page: import('playwright').Page, orderEl: unknown) => Promise<object[]>} extractOrderItems
 * @property {(page: import('playwright').Page, orderEl: unknown) => Promise<object[]>} extractShipments
 * @property {(page: import('playwright').Page, orderEl: unknown) => Promise<object[]>} extractReturns
 * @property {(page: import('playwright').Page, orderEl: unknown) => Promise<object[]>} extractRefunds
 * @property {(raw: object) => object} normalize
 * @property {(order: object) => {valid: boolean, errors: string[]}} validate
 * @property {(order: object) => object} redact
 * @property {() => string} getVersion
 */

const REQUIRED_METHODS = [
    'supportsCurrentPage', 'detectPageType', 'extractOrders', 'extractOrderItems',
    'extractShipments', 'extractReturns', 'extractRefunds', 'normalize', 'validate',
    'redact', 'getVersion',
];

/** Throws if `parser` is missing any required method - fails fast in dev/tests. */
export function assertImplementsParserInterface(parser) {
    const missing = REQUIRED_METHODS.filter((method) => typeof parser[method] !== 'function');

    if (missing.length > 0) {
        throw new Error(`Parser is missing required method(s): ${missing.join(', ')}`);
    }
}
