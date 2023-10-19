/* eslint-disable */
import type { Context, APIGatewayProxyCallback } from "aws-lambda"

export declare interface EventPayload {
  content: string
}

function parseEvent(event): EventPayload {
  try {
    if ('body' in event) {
      return JSON.parse(event.body)
    }

    return event
  } catch {
    throw Error("Cannot parse content " + event)
  }
}

function handler(event, context: Context, callback: APIGatewayProxyCallback): void {
  let error = null;
  let body = {};

  try {
    body = parseEvent(event)
  } catch (e) {
    error = e
  }

  callback(error, {
    statusCode: 200,
    body: JSON.stringify(body, null),
  });
}

// exports.handler = AWSLambda.wrapHandler(handler)

export { handler }
