import type { Request } from "./client";
import type { Page } from "./models";

export interface Conversation {
  id: string;
  title: string;
  updated_at: string;
}
export interface ChatMessage {
  id: string;
  role: "user" | "assistant";
  content: string;
  status: string;
  created_at: string;
}
export type ChatEvent = { type: "text"; text: string } | { type: "done" };
export interface StreamConnection {
  chunks: AsyncIterable<string>;
  cancel(): void;
}
export type StreamRequest = (path: string, data: unknown) => StreamConnection;
export interface ByteStream {
  getReader(): {
    read(): Promise<{ done: boolean; value?: Uint8Array }>;
    cancel(): Promise<void>;
    releaseLock(): void;
  };
}
export interface Utf8Decoder {
  decode(input?: Uint8Array, options?: { stream?: boolean }): string;
}
// Apps supply their runtime's byte stream and UTF-8 decoder.
export async function* decodeStream(
  body: ByteStream,
  decoder: Utf8Decoder,
): AsyncGenerator<string> {
  const reader = body.getReader();
  let ended = false;
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        ended = true;
        const tail = decoder.decode();
        if (tail) yield tail;
        return;
      }
      yield decoder.decode(value, { stream: true });
    }
  } finally {
    if (!ended) await reader.cancel().catch(() => undefined);
    reader.releaseLock();
  }
}
const path = "/api/v1/ai/conversations";

export function copilotOperations(request: Request, stream?: StreamRequest) {
  return {
    list: (page = 1) => request<Page<Conversation>>(`${path}?page=${page}`),
    start: async () =>
      (await request<{ data: Conversation }>(path, "POST")).data,
    messages: (id: string, page = 1) =>
      request<Page<ChatMessage>>(
        `${path}/${encodeURIComponent(id)}/messages?page=${page}`,
      ),
    remove: (id: string) =>
      request<void>(`${path}/${encodeURIComponent(id)}`, "DELETE"),
    reply(id: string, message: string) {
      if (!stream) throw new Error("Chat streaming is unavailable.");
      const connection = stream(`${path}/${encodeURIComponent(id)}/messages`, {
        message,
      });
      return {
        events: readChatEvents(connection.chunks),
        cancel: connection.cancel,
      };
    },
  };
}

// Read our small public event contract; provider events never reach either app.
export async function* readChatEvents(
  chunks: AsyncIterable<string>,
): AsyncGenerator<ChatEvent> {
  let buffer = "";
  for await (const chunk of chunks) {
    buffer += chunk;
    if (buffer.length > 65536)
      throw new Error("The chat response was too large.");
    let end: number;
    while ((end = buffer.indexOf("\n\n")) !== -1) {
      const frame = buffer.slice(0, end);
      buffer = buffer.slice(end + 2);
      const data = frame
        .split("\n")
        .filter((line) => line.startsWith("data:"))
        .map((line) => line.slice(5).trimStart())
        .join("\n");
      if (!data) continue;
      const event: unknown = JSON.parse(data);
      if (typeof event !== "object" || event === null || !("type" in event))
        throw new Error("Invalid chat response.");
      if (
        event.type === "error" &&
        "message" in event &&
        typeof event.message === "string"
      )
        throw new Error(event.message);
      if (event.type === "done") {
        yield { type: "done" };
        return;
      }
      if (
        event.type !== "text" ||
        !("text" in event) ||
        typeof event.text !== "string"
      )
        throw new Error("Invalid chat response.");
      yield { type: "text", text: event.text };
    }
  }
  throw new Error("The reply was interrupted. Please retry.");
}
