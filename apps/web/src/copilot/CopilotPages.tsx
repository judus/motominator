import { useEffect, useState } from "react";
import { Link, Route, Routes, useNavigate, useParams } from "react-router";
import {
  Alert,
  Button,
  Group,
  Paper,
  Stack,
  Text,
  Textarea,
} from "@mantine/core";
import { useCopilot } from "@motominator/client/react";
import { Page } from "../ui/Page";

function ConversationList({ verified }: { verified: boolean }) {
  const chat = useCopilot();
  const navigate = useNavigate();
  const { loadConversations } = chat;
  useEffect(() => {
    void loadConversations();
  }, [loadConversations]);
  return (
    <Page
      title="Copilot"
      description="Talk about your motorcycles and recorded history."
    >
      <Text c="dimmed">
        Messages and requested garage records are sent to your chosen AI
        provider. Conversations are saved to your account.
      </Text>
      {!verified && (
        <Alert>Please verify your email before starting a chat.</Alert>
      )}
      <Group>
        <Button
          disabled={!verified || chat.busy}
          onClick={() =>
            void chat.start().then((id) => {
              if (id) navigate(`/copilot/${id}`);
            })
          }
        >
          New conversation
        </Button>
        <Button component={Link} to="/account/ai" variant="outline">
          AI settings
        </Button>
      </Group>
      {chat.listLoading && <Text role="status">Loading conversations…</Text>}
      {chat.listError && (
        <Alert color="red" role="alert">
          {chat.listError}
          <Button
            variant="outline"
            onClick={() => void chat.loadConversations()}
          >
            Retry
          </Button>
        </Alert>
      )}
      {chat.error && (
        <Alert color="red" role="alert">
          {chat.error}
        </Alert>
      )}
      {chat.listReady &&
        !chat.listLoading &&
        !chat.listError &&
        !chat.conversations.length && (
          <Text>No conversations yet. Start one to ask about your garage.</Text>
        )}
      <Stack>
        {chat.conversations.map((conversation) => (
          <Paper withBorder p="md" key={conversation.id}>
            <Button
              component={Link}
              to={`/copilot/${conversation.id}`}
              variant="outline"
              disabled={chat.busy}
            >
              {conversation.title}
            </Button>
          </Paper>
        ))}
      </Stack>
      {chat.hasMoreConversations && (
        <Button
          variant="outline"
          disabled={chat.listLoading}
          onClick={() => void chat.loadMoreConversations()}
        >
          Load more conversations
        </Button>
      )}
    </Page>
  );
}

function ConversationPage({ verified }: { verified: boolean }) {
  const { conversationId = "" } = useParams();
  const chat = useCopilot();
  const navigate = useNavigate();
  const [confirmDelete, setConfirmDelete] = useState(false);
  const { select } = chat;
  useEffect(() => {
    void select(conversationId);
  }, [conversationId, select]);
  const selected = chat.conversationId === conversationId;
  return (
    <Page title="Conversation" parent={{ to: "/copilot", label: "copilot" }}>
      <Group>
        <Button component={Link} to="/account/ai" variant="outline">
          AI settings
        </Button>
        <Button
          variant="outline"
          color="red"
          disabled={chat.busy || !selected}
          onClick={() => setConfirmDelete(true)}
        >
          Delete conversation
        </Button>
      </Group>
      {!verified && (
        <Alert>Please verify your email before sending messages.</Alert>
      )}
      {(!selected || chat.loading) && (
        <Text role="status">Loading messages…</Text>
      )}
      {selected && chat.hasOlderMessages && (
        <Button
          variant="outline"
          disabled={chat.busy || chat.loading}
          onClick={() => void chat.loadOlder()}
        >
          Load older messages
        </Button>
      )}
      {selected && chat.historyReady && !chat.messages.length && (
        <Text>Try “What maintenance have I recorded for my motorcycle?”</Text>
      )}
      <Stack aria-label="Conversation messages">
        {selected &&
          chat.messages.map((message) => (
            <Paper
              withBorder
              p="md"
              key={message.id}
              maw="90%"
              ml={message.role === "user" ? "auto" : undefined}
            >
              <Text size="sm" fw={600}>
                {message.role === "user" ? "You" : "Copilot"}
              </Text>
              <Text style={{ whiteSpace: "pre-wrap" }}>
                {message.content || "Thinking…"}
              </Text>
              {message.status === "failed" || message.status === "stopped" ? (
                <Text size="sm" c="dimmed">
                  Incomplete reply
                </Text>
              ) : null}
            </Paper>
          ))}
      </Stack>
      {selected && chat.error && (
        <Alert color="red" role="alert">
          {chat.error}
        </Alert>
      )}
      {selected && !chat.historyReady && !chat.loading && (
        <Button variant="outline" onClick={() => void chat.retryHistory()}>
          Retry loading messages
        </Button>
      )}
      <Text role="status" c="dimmed">
        {selected ? chat.status : ""}
      </Text>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          void chat.send();
        }}
      >
        <Stack>
          <Textarea
            label="Message"
            placeholder="Ask about your garage…"
            value={selected ? chat.draft : ""}
            onChange={(event) => chat.setDraft(event.currentTarget.value)}
            autosize
            minRows={2}
            maxRows={6}
            maxLength={8000}
            disabled={!selected || !chat.historyReady || !verified || chat.busy}
          />
          <Group>
            <Button
              type="submit"
              disabled={
                !selected ||
                !chat.historyReady ||
                !verified ||
                chat.busy ||
                !chat.draft.trim()
              }
            >
              Send message
            </Button>
            {chat.replying && (
              <Button variant="outline" onClick={chat.stop}>
                Stop reply
              </Button>
            )}
          </Group>
        </Stack>
      </form>
      {confirmDelete && (
        <Paper
          withBorder
          p="md"
          component="section"
          aria-label="Delete conversation confirmation"
        >
          <Stack>
            <Text>This removes the saved conversation and its messages.</Text>
            <Button variant="outline" onClick={() => setConfirmDelete(false)}>
              Cancel deletion
            </Button>
            <Button
              variant="outline"
              color="red"
              disabled={chat.busy}
              onClick={() =>
                void chat.remove(conversationId).then((removed) => {
                  if (removed) navigate("/copilot", { replace: true });
                  setConfirmDelete(false);
                })
              }
            >
              Confirm deletion
            </Button>
          </Stack>
        </Paper>
      )}
    </Page>
  );
}

export function CopilotPages({ verified }: { verified: boolean }) {
  return (
    <Routes>
      <Route index element={<ConversationList verified={verified} />} />
      <Route
        path=":conversationId"
        element={<ConversationPage verified={verified} />}
      />
    </Routes>
  );
}
