import {
  createContext,
  createElement,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import type { Client } from "../client";
import type { ChatMessage, Conversation } from "../copilot";
import { failureMessage } from "../errors";

function useCopilotSession(client: Client) {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [draft, setDraft] = useState("");
  const [error, setError] = useState("");
  const [replying, setReplying] = useState(false);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(false);
  const [listReady, setListReady] = useState(false);
  const [historyReady, setHistoryReady] = useState(false);
  const [listLoading, setListLoading] = useState(false);
  const [listError, setListError] = useState("");
  const [historyPage, setHistoryPage] = useState(1);
  const [historyLastPage, setHistoryLastPage] = useState(1);
  const [listPage, setListPage] = useState(1);
  const [listLastPage, setListLastPage] = useState(1);
  const [status, setStatus] = useState("");
  const alive = useRef(false);
  const generation = useRef(0);
  const selection = useRef<string | null>(null);
  const pending = useRef(false);
  const listing = useRef(false);
  const reading = useRef(false);
  const abort = useRef<{ cancel(): void } | null>(null);

  const dispose = useCallback(() => {
    alive.current = false;
    generation.current++;
    abort.current?.cancel();
    selection.current = null;
    abort.current = null;
    pending.current = false;
  }, []);
  useEffect(() => {
    alive.current = true;
    return dispose;
  }, [client, dispose]);

  const loadConversations = useCallback(
    async (page = 1) => {
      if (listing.current) return;
      listing.current = true;
      setListLoading(true);
      setListError("");
      try {
        const result = await client.copilot.list(page);
        if (!alive.current) return;
        setConversations((previous) =>
          page === 1
            ? result.data
            : [
                ...previous,
                ...result.data.filter(
                  (item) => !previous.some((old) => old.id === item.id),
                ),
              ],
        );
        setListPage(result.meta.current_page);
        setListLastPage(result.meta.last_page);
      } catch (failure) {
        if (alive.current)
          setListError(
            failureMessage(failure, "Unable to load conversations."),
          );
      } finally {
        listing.current = false;
        if (alive.current) {
          setListLoading(false);
          setListReady(true);
        }
      }
    },
    [client],
  );

  const readHistory = useCallback(
    async function readHistory(id: string, page: number, current: number) {
      const result = await client.copilot.messages(id, page);
      if (!alive.current || current !== generation.current) return;
      const ordered = [...result.data].reverse();
      setMessages((previous) =>
        page === 1
          ? ordered
          : [
              ...ordered.filter(
                (item) => !previous.some((old) => old.id === item.id),
              ),
              ...previous,
            ],
      );
      setHistoryPage(result.meta.current_page);
      setHistoryLastPage(result.meta.last_page);
      setHistoryReady(true);
    },
    [client],
  );

  const select = useCallback(
    async (id: string) => {
      if (selection.current === id) return;
      abort.current?.cancel();
      const current = ++generation.current;
      selection.current = id;
      setConversationId(id);
      setMessages([]);
      setHistoryReady(false);
      setDraft("");
      setError("");
      setStatus("");
      setLoading(true);
      setBusy(false);
      setReplying(false);
      pending.current = false;
      try {
        await readHistory(id, 1, current);
      } catch (failure) {
        if (alive.current && current === generation.current)
          setError(
            failureMessage(failure, "Unable to load this conversation."),
          );
      } finally {
        if (alive.current && current === generation.current) setLoading(false);
      }
    },
    [readHistory],
  );

  async function start(): Promise<string | null> {
    if (pending.current) return null;
    pending.current = true;
    setBusy(true);
    setError("");
    const current = generation.current;
    try {
      const conversation = await client.copilot.start();
      if (!alive.current || current !== generation.current) return null;
      setConversations((previous) => [conversation, ...previous]);
      await select(conversation.id);
      if (!alive.current || selection.current !== conversation.id) return null;
      return conversation.id;
    } catch (failure) {
      if (alive.current && current === generation.current)
        setError(failureMessage(failure, "Unable to start a conversation."));
      return null;
    } finally {
      if (alive.current && current === generation.current) {
        pending.current = false;
        setBusy(false);
      }
    }
  }

  async function send() {
    const id = selection.current;
    const message = draft.trim();
    if (
      !id ||
      !historyReady ||
      !message ||
      message.length > 8000 ||
      pending.current ||
      loading
    )
      return;
    pending.current = true;
    const current = generation.current;
    let stopped = false;
    let connection: ReturnType<Client["copilot"]["reply"]> | null = null;
    abort.current = {
      cancel: () => {
        stopped = true;
        connection?.cancel();
      },
    };
    setBusy(true);
    setError("");
    setStatus("Thinking…");
    setReplying(true);
    setDraft("");
    const localId = `local-${Date.now()}`;
    const created_at = new Date().toISOString();
    setMessages((previous) => [
      ...previous,
      {
        id: `${localId}-user`,
        role: "user",
        content: message,
        status: "pending",
        created_at,
      },
      {
        id: localId,
        role: "assistant",
        content: "",
        status: "pending",
        created_at,
      },
    ]);
    try {
      connection = client.copilot.reply(id, message);
      for await (const event of connection.events) {
        if (!alive.current || current !== generation.current) return;
        if (event.type === "text") {
          setStatus("Replying…");
          setMessages((previous) =>
            previous.map((item) =>
              item.id === localId
                ? { ...item, content: item.content + event.text }
                : item,
            ),
          );
        }
      }
      if (!alive.current || current !== generation.current) return;
      setStatus("Reply complete.");
      setMessages((previous) =>
        previous.map((item) =>
          item.id.startsWith(localId) ? { ...item, status: "completed" } : item,
        ),
      );
      // Refresh saved IDs without discarding a successful reply if the refresh fails.
      try {
        await readHistory(id, 1, current);
      } catch {
        if (alive.current && current === generation.current)
          setStatus("Reply complete. Saved history could not be refreshed.");
      }
      void loadConversations();
    } catch (failure) {
      if (!alive.current || current !== generation.current) return;
      setDraft((previous) => previous || message);
      setStatus(
        stopped
          ? "Reply stopped. Please wait a moment before retrying."
          : "Reply failed.",
      );
      if (!stopped) setError(failureMessage(failure, "Unable to get a reply."));
      setMessages((previous) =>
        previous.map((item) =>
          item.id.startsWith(localId)
            ? { ...item, status: stopped ? "stopped" : "failed" }
            : item,
        ),
      );
    } finally {
      if (current === generation.current) {
        pending.current = false;
        abort.current = null;
        if (alive.current) {
          setBusy(false);
          setReplying(false);
        }
      }
    }
  }

  async function loadOlder() {
    const id = selection.current;
    if (!id || pending.current || reading.current) return;
    reading.current = true;
    const current = generation.current;
    setLoading(true);
    setError("");
    try {
      await readHistory(id, historyPage + 1, current);
    } catch (failure) {
      if (alive.current && current === generation.current)
        setError(failureMessage(failure, "Unable to load older messages."));
    } finally {
      reading.current = false;
      if (alive.current && current === generation.current) setLoading(false);
    }
  }

  async function retryHistory() {
    const id = selection.current;
    if (!id || pending.current || reading.current) return;
    reading.current = true;
    const current = generation.current;
    setLoading(true);
    setError("");
    try {
      await readHistory(id, 1, current);
    } catch (failure) {
      if (alive.current && current === generation.current)
        setError(failureMessage(failure, "Unable to load conversation."));
    } finally {
      reading.current = false;
      if (alive.current && current === generation.current) setLoading(false);
    }
  }

  async function remove(id: string): Promise<boolean> {
    if (pending.current) return false;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      await client.copilot.remove(id);
      if (!alive.current) return false;
      setConversations((previous) => previous.filter((item) => item.id !== id));
      if (selection.current === id) {
        generation.current++;
        selection.current = null;
        setConversationId(null);
        setMessages([]);
        setDraft("");
      }
      return true;
    } catch (failure) {
      if (alive.current)
        setError(failureMessage(failure, "Unable to delete conversation."));
      return false;
    } finally {
      pending.current = false;
      if (alive.current) setBusy(false);
    }
  }

  return {
    conversations,
    conversationId,
    messages,
    draft,
    setDraft,
    error,
    busy,
    replying,
    loading,
    listReady,
    historyReady,
    listLoading,
    listError,
    status,
    start,
    select,
    send,
    remove,
    retryHistory,
    loadConversations,
    loadOlder,
    loadMoreConversations: () => loadConversations(listPage + 1),
    hasMoreConversations: listPage < listLastPage,
    hasOlderMessages: historyPage < historyLastPage,
    stop: () => abort.current?.cancel(),
  };
}

const CopilotContext = createContext<ReturnType<
  typeof useCopilotSession
> | null>(null);
export function CopilotProvider({
  client,
  children,
}: {
  client: Client;
  children: ReactNode;
}) {
  const session = useCopilotSession(client);
  return createElement(CopilotContext.Provider, { value: session }, children);
}
export function useCopilot() {
  const session = useContext(CopilotContext);
  if (!session) throw new Error("CopilotProvider is required.");
  return session;
}
