function parseEvent(event) {
  try {
    if ('body' in event) {
      return JSON.parse(event.body);
    }

    return event;
  } catch (_unused) {
    throw Error("Cannot parse content " + event);
  }
}

function handler(event, context, callback) {
  let error = null;
  let body = {};

  try {
    body = parseEvent(event);
  } catch (e) {
    error = e;
  }

  callback(error, {
    statusCode: 200,
    body: JSON.stringify(body, null)
  });
} // exports.handler = AWSLambda.wrapHandler(handler)

exports.handler = handler;
