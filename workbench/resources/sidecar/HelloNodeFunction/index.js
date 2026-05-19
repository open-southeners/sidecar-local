'use strict';

exports.handler = async function handler(event = {}) {
    const name = event.name ?? 'world';

    return {
        message: `Hello, ${name} from Node.js`,
        runtime: 'nodejs20.x',
    };
};
