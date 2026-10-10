import { act, renderHook, waitFor } from "@testing-library/react";
import { expect, it, vi } from "vitest";
import {
  createClient,
  readChatEvents,
  decodeStream,
  type ChatMessage,
  type Request,
  type StreamRequest,
} from "@motominator/client";
import { CopilotProvider, useCopilot } from "@motominator/client/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { StrictMode, type ReactNode } from "react";
import { render, fireEvent, screen } from "../test/render";
import { CopilotPages } from "./CopilotPages";

const id = "019a0000-0000-7000-8000-000000000001";
const conversation = {
  id,
  title: "My garage",
  updated_at: "2026-10-10T12:00:00Z",
};
const page = <T,>(data: T[]) => ({
  data,
  meta: { current_page: 1, last_page: 1 },
});
function setup(stream: StreamRequest, saved: ChatMessage[] = []) {
  const request = vi.fn(async (path: string, method?: string) => {
    if (method === "POST") return { data: conversation };
    return path.includes("/messages") ? page(saved) : page([conversation]);
  }) as ReturnType<typeof vi.fn> & Request;
  const client = createClient(request, undefined, stream);
  const wrapper = ({ children }: { children: ReactNode }) => (
    <CopilotProvider client={client}>{children}</CopilotProvider>
  );
  return { request, client, wrapper };
}

it("reads split events, rejects truncated streams and forwards safe errors", async () => {
  async function* chunks() {
    yield 'data: {"type":"te';
    yield 'xt","text":"Hello"}\n\ndata: {"type":"done"}\n\n';
  }
  const events = [];
  for await (const event of readChatEvents(chunks())) events.push(event);
  expect(events).toEqual([{ type: "text", text: "Hello" }, { type: "done" }]);
  async function* incomplete() {
    yield 'data: {"type":"text","text":"partial"}\n\n';
  }
  await expect(async () => {
    for await (const event of readChatEvents(incomplete())) void event;
  }).rejects.toThrow("interrupted");
  async function* failure() {
    yield 'data: {"type":"error","message":"Safe provider failure"}\n\n';
  }
  await expect(async () => {
    for await (const event of readChatEvents(failure())) void event;
  }).rejects.toThrow("Safe provider failure");
});

it("decodes UTF-8 split across byte chunks and closes the reader", async () => {
  const bytes = new TextEncoder().encode("révision 🏍️");
  let offset = 0;
  const releaseLock = vi.fn();
  const cancel = vi.fn();
  const stream = {
    getReader: () => ({
      read: async () =>
        offset === bytes.length
          ? { done: true }
          : { done: false, value: bytes.slice(offset, ++offset) },
      releaseLock,
      cancel,
    }),
  };
  let decoded = "";
  for await (const chunk of decodeStream(stream, new TextDecoder()))
    decoded += chunk;
  expect(decoded).toBe("révision 🏍️");
  expect(releaseLock).toHaveBeenCalledOnce();
  expect(cancel).not.toHaveBeenCalled();
});

it("keeps a failed draft and partial reply without automatically spending on a retry", async () => {
  const stream = vi.fn(() => ({
    chunks: (async function* () {
      yield 'data: {"type":"text","text":"Partial answer"}\n\n';
      throw new Error("Connection lost");
    })(),
    cancel: vi.fn(),
  }));
  const { wrapper } = setup(stream);
  const { result } = renderHook(() => useCopilot(), { wrapper });
  await act(async () => {
    await result.current.select(id);
  });
  act(() => result.current.setDraft("What maintenance is recorded?"));
  await act(async () => {
    await result.current.send();
  });
  expect(result.current.draft).toBe("What maintenance is recorded?");
  expect(result.current.error).toBe("Connection lost");
  expect(result.current.messages.at(-1)?.content).toBe("Partial answer");
  expect(stream).toHaveBeenCalledOnce();
});

it("stops a pending stream and restores the submitted draft", async () => {
  let reject: (failure: Error) => void = () => {};
  const pending = new Promise<void>((_resolve, fail) => {
    reject = fail;
  });
  const cancel = vi.fn(() => reject(new Error("Aborted")));
  const { wrapper } = setup(() => ({
    chunks: (async function* () {
      await pending;
      yield "";
    })(),
    cancel,
  }));
  const { result, unmount } = renderHook(() => useCopilot(), { wrapper });
  await act(async () => {
    await result.current.select(id);
  });
  act(() => result.current.setDraft("hello"));
  let sending: Promise<void>;
  act(() => {
    sending = result.current.send();
  });
  await waitFor(() => expect(result.current.busy).toBe(true));
  act(() => result.current.stop());
  await act(async () => {
    await sending;
  });
  expect(cancel).toHaveBeenCalledOnce();
  expect(result.current.status).toContain("stopped");
  expect(result.current.draft).toBe("hello");
  unmount();
});

it("cancels an in-flight reply when the account session unmounts", async () => {
  let reject: (failure: Error) => void = () => {};
  const pending = new Promise<void>((_resolve, fail) => {
    reject = fail;
  });
  const cancel = vi.fn(() => reject(new Error("Aborted")));
  const { wrapper } = setup(() => ({
    chunks: (async function* () {
      await pending;
      yield "";
    })(),
    cancel,
  }));
  const { result, unmount } = renderHook(() => useCopilot(), { wrapper });
  await act(async () => {
    await result.current.select(id);
  });
  act(() => result.current.setDraft("hello"));
  let sending: Promise<void>;
  act(() => {
    sending = result.current.send();
  });
  unmount();
  await act(async () => {
    await sending;
  });
  expect(cancel).toHaveBeenCalledOnce();
});

it("does not replace a newer selection when conversation creation finishes late", async () => {
  const { client, wrapper } = setup(() => ({
    chunks: (async function* () {
      yield 'data: {"type":"done"}\n\n';
    })(),
    cancel: vi.fn(),
  }));
  let resolve: (value: typeof conversation) => void = () => {};
  client.copilot.start = vi.fn(
    () =>
      new Promise<typeof conversation>((done) => {
        resolve = done;
      }),
  );
  const { result } = renderHook(() => useCopilot(), { wrapper });
  let starting: Promise<string | null>;
  act(() => {
    starting = result.current.start();
  });
  await act(async () => {
    await result.current.select("newer-selection");
  });
  await act(async () => {
    resolve(conversation);
    expect(await starting).toBeNull();
  });
  expect(result.current.conversationId).toBe("newer-selection");
});

it("ignores a late history response after switching conversations", async () => {
  const { client } = setup(() => ({
    chunks: (async function* () {
      yield 'data: {"type":"done"}\n\n';
    })(),
    cancel: vi.fn(),
  }));
  let resolveFirst: (
    value: ReturnType<typeof page<ChatMessage>>,
  ) => void = () => {};
  client.copilot.messages = vi.fn(async (requested) =>
    requested === id
      ? await new Promise<ReturnType<typeof page<ChatMessage>>>((resolve) => {
          resolveFirst = resolve;
        })
      : page([]),
  );
  const wrapper = ({ children }: { children: ReactNode }) => (
    <CopilotProvider client={client}>{children}</CopilotProvider>
  );
  const { result } = renderHook(() => useCopilot(), { wrapper });
  let first: Promise<void>;
  act(() => {
    first = result.current.select(id);
  });
  await act(async () => {
    await result.current.select("another-conversation");
  });
  await act(async () => {
    resolveFirst(
      page([
        {
          id: "private-old-message",
          role: "assistant",
          content: "Old conversation",
          status: "completed",
          created_at: "2026-10-10",
        },
      ]),
    );
    await first;
  });
  expect(result.current.conversationId).toBe("another-conversation");
  expect(result.current.messages).toEqual([]);
});

it("loads a direct chat through development remounts and submits the controlled draft", async () => {
  const stream = vi.fn(() => ({
    chunks: (async function* () {
      yield 'data: {"type":"done"}\n\n';
    })(),
    cancel: vi.fn(),
  }));
  const { client } = setup(stream);
  render(
    <StrictMode>
      <MemoryRouter initialEntries={[`/copilot/${id}`]}>
        <CopilotProvider client={client}>
          <Routes>
            <Route path="/copilot/*" element={<CopilotPages verified />} />
          </Routes>
        </CopilotProvider>
      </MemoryRouter>
    </StrictMode>,
  );
  const input = await screen.findByLabelText("Message");
  await waitFor(() => expect(input).toBeEnabled());
  fireEvent.change(input, {
    target: { value: "Summarize my recorded history" },
  });
  const send = screen.getByRole("button", { name: "Send message" });
  expect(send).toBeEnabled();
  fireEvent.click(send);
  await waitFor(() =>
    expect(stream).toHaveBeenCalledWith(
      `/api/v1/ai/conversations/${id}/messages`,
      { message: "Summarize my recorded history" },
    ),
  );
});

it("offers dedicated conversation routes and requires confirmation before deletion", async () => {
  const { client, request } = setup(() => ({
    chunks: (async function* () {
      yield 'data: {"type":"done"}\n\n';
    })(),
    cancel: vi.fn(),
  }));
  render(
    <MemoryRouter initialEntries={["/copilot"]}>
      <CopilotProvider client={client}>
        <Routes>
          <Route path="/copilot/*" element={<CopilotPages verified />} />
        </Routes>
      </CopilotProvider>
    </MemoryRouter>,
  );
  fireEvent.click(await screen.findByRole("link", { name: "My garage" }));
  await screen.findByLabelText("Message");
  await waitFor(() => expect(screen.getByLabelText("Message")).toBeEnabled());
  fireEvent.click(screen.getByRole("button", { name: "Delete conversation" }));
  expect(request).not.toHaveBeenCalledWith(expect.any(String), "DELETE");
  fireEvent.click(
    await screen.findByRole("button", { name: "Confirm deletion" }),
  );
  await waitFor(() =>
    expect(request).toHaveBeenCalledWith(
      `/api/v1/ai/conversations/${id}`,
      "DELETE",
    ),
  );
});
