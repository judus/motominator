import { useCallback, useRef } from "react";
import { Alert, FlatList, KeyboardAvoidingView, Platform } from "react-native";
import {
  Link,
  router,
  useFocusEffect,
  useLocalSearchParams,
} from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { getTokens, useTheme } from "tamagui";
import { useCopilot } from "@motominator/client/react";
import type { ChatMessage, Conversation } from "@motominator/client";
import { useAuth } from "@/auth/auth-context";
import { Button, Column, Field, H2, Section, Text } from "@/ui/components";

export function CopilotListScreen() {
  const chat = useCopilot();
  const theme = useTheme();
  const spacing = getTokens().space;
  const { user } = useAuth();
  const { loadConversations } = chat;
  useFocusEffect(
    useCallback(() => {
      void loadConversations();
    }, [loadConversations]),
  );
  return (
    <SafeAreaView
      edges={["left", "right"]}
      style={{ flex: 1, backgroundColor: theme.background.val }}
    >
      <FlatList<Conversation>
        data={chat.conversations}
        keyExtractor={(item) => item.id}
        contentInsetAdjustmentBehavior="automatic"
        contentContainerStyle={{ padding: spacing.$4.val, gap: spacing.$3.val }}
        ListHeaderComponent={
          <Column gap="$3">
            <H2>Copilot</H2>
            <Text>Talk about your motorcycles and recorded history.</Text>
            <Text color="$color11">
              Messages and requested garage records are sent to your chosen AI
              provider. Conversations are saved to your account.
            </Text>
            {!user?.email_verified_at && (
              <Text>Please verify your email before starting a chat.</Text>
            )}
            <Button
              label="New conversation"
              disabled={!user?.email_verified_at || chat.busy}
              onPress={() =>
                void chat.start().then((id) => {
                  if (id) router.push(`/copilot/${id}`);
                })
              }
            />
            <Link href="/account/ai" asChild>
              <Button intent="secondary" label="AI settings" />
            </Link>
            {chat.listLoading && (
              <Text accessibilityRole="alert">Loading conversations…</Text>
            )}
            {!!chat.listError && (
              <Column gap="$2">
                <Text accessibilityRole="alert">{chat.listError}</Text>
                <Button
                  intent="secondary"
                  label="Retry"
                  onPress={() => void chat.loadConversations()}
                />
              </Column>
            )}
            {!!chat.error && (
              <Text accessibilityRole="alert">{chat.error}</Text>
            )}
          </Column>
        }
        ListEmptyComponent={
          chat.listReady && !chat.listLoading && !chat.listError ? (
            <Text>
              No conversations yet. Start one to ask about your garage.
            </Text>
          ) : null
        }
        renderItem={({ item }) => (
          <Section>
            <Link href={`/copilot/${item.id}`} asChild>
              <Button
                intent="secondary"
                label={item.title}
                disabled={chat.busy}
              />
            </Link>
          </Section>
        )}
        ListFooterComponent={
          chat.hasMoreConversations ? (
            <Button
              intent="secondary"
              label="Load more conversations"
              disabled={chat.listLoading}
              onPress={() => void chat.loadMoreConversations()}
            />
          ) : null
        }
      />
    </SafeAreaView>
  );
}

export function CopilotConversationScreen() {
  const { conversationId } = useLocalSearchParams<{ conversationId: string }>();
  const chat = useCopilot();
  const { user } = useAuth();
  const theme = useTheme();
  const spacing = getTokens().space;
  const list = useRef<FlatList<ChatMessage>>(null);
  const follow = useRef(true);
  const { select } = chat;
  useFocusEffect(
    useCallback(() => {
      follow.current = true;
      void select(conversationId);
    }, [conversationId, select]),
  );
  const selected = chat.conversationId === conversationId;
  function remove() {
    Alert.alert(
      "Delete conversation?",
      "This removes the saved conversation and its messages.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Delete",
          style: "destructive",
          onPress: () =>
            void chat.remove(conversationId).then((removed) => {
              if (removed) router.replace("/copilot");
            }),
        },
      ],
    );
  }
  return (
    <SafeAreaView
      edges={["left", "right"]}
      style={{ flex: 1, backgroundColor: theme.background.val }}
    >
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <FlatList<ChatMessage>
          ref={list}
          data={selected ? chat.messages : []}
          keyExtractor={(item) => item.id}
          contentInsetAdjustmentBehavior="automatic"
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={{
            padding: spacing.$4.val,
            gap: spacing.$3.val,
          }}
          maintainVisibleContentPosition={{ minIndexForVisible: 0 }}
          onScroll={({ nativeEvent }) => {
            follow.current =
              nativeEvent.contentSize.height -
                nativeEvent.layoutMeasurement.height -
                nativeEvent.contentOffset.y <
              80;
          }}
          onContentSizeChange={() => {
            if (follow.current) list.current?.scrollToEnd({ animated: false });
          }}
          ListHeaderComponent={
            <Column gap="$3">
              <Link href="/account/ai" asChild>
                <Button intent="secondary" label="AI settings" />
              </Link>
              <Button
                intent="danger"
                label="Delete conversation"
                disabled={chat.busy || !selected}
                onPress={remove}
              />
              {(!selected || chat.loading) && <Text>Loading messages…</Text>}
              {selected && chat.hasOlderMessages && (
                <Button
                  intent="secondary"
                  label="Load older messages"
                  disabled={chat.loading || chat.busy}
                  onPress={() => {
                    follow.current = false;
                    void chat.loadOlder();
                  }}
                />
              )}
              {selected && !chat.historyReady && !chat.loading && (
                <Button
                  intent="secondary"
                  label="Retry loading messages"
                  onPress={() => void chat.retryHistory()}
                />
              )}
            </Column>
          }
          ListEmptyComponent={
            selected && chat.historyReady ? (
              <Text>
                Try “What maintenance have I recorded for my motorcycle?”
              </Text>
            ) : null
          }
          renderItem={({ item }) => (
            <Section
              alignSelf={item.role === "user" ? "flex-end" : "flex-start"}
              maxWidth="90%"
            >
              <Text fontWeight="600">
                {item.role === "user" ? "You" : "Copilot"}
              </Text>
              <Text selectable>{item.content || "Thinking…"}</Text>
              {(item.status === "failed" || item.status === "stopped") && (
                <Text color="$color11">Incomplete reply</Text>
              )}
            </Section>
          )}
        />
        <Column
          padding="$4"
          gap="$2"
          borderTopWidth={1}
          borderColor="$borderColor"
        >
          {!!chat.error && selected && (
            <Text accessibilityRole="alert">{chat.error}</Text>
          )}
          {!!chat.status && selected && (
            <Text color="$color11">{chat.status}</Text>
          )}
          {!user?.email_verified_at && (
            <Text>Please verify your email before sending messages.</Text>
          )}
          <Field
            id="chat-message"
            label="Message"
            placeholder="Ask about your garage…"
            multiline
            height="$7"
            maxLength={8000}
            value={selected ? chat.draft : ""}
            onChangeText={chat.setDraft}
            disabled={
              !selected ||
              !chat.historyReady ||
              !user?.email_verified_at ||
              chat.busy
            }
          />
          {chat.replying ? (
            <Button intent="secondary" label="Stop reply" onPress={chat.stop} />
          ) : (
            <Button
              label="Send message"
              disabled={
                !selected ||
                !chat.historyReady ||
                chat.busy ||
                !user?.email_verified_at ||
                !chat.draft.trim()
              }
              onPress={() => {
                follow.current = true;
                void chat.send();
              }}
            />
          )}
        </Column>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
