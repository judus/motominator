import {
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react-native";
import { CopilotProvider } from "@motominator/client/react";
import { createClient, type Request } from "@motominator/client";
import { CopilotConversationScreen } from "../src/copilot/copilot-screens";

jest.mock("tamagui", () => jest.requireActual("../test/tamagui-mock"));
jest.mock("react-native-safe-area-context", () => ({
  SafeAreaView: jest.requireActual("react-native").View,
}));
jest.mock("../src/auth/auth-context", () => ({
  useAuth: () => ({ user: { email_verified_at: "2026-10-10" } }),
}));
jest.mock("expo-router", () => ({
  useFocusEffect: (callback: () => void) =>
    jest.requireActual("react").useEffect(callback, [callback]),
  useLocalSearchParams: () => ({ conversationId: "chat-one" }),
  Link: ({ children }: { children: React.ReactNode }) => children,
  router: { push: jest.fn(), replace: jest.fn() },
}));

it("sends through the shared client and preserves the draft after a provider failure", async () => {
  const request = jest.fn(async () => ({
    data: [],
    meta: { current_page: 1, last_page: 1 },
  })) as jest.Mock & Request;
  const stream = jest.fn(() => ({
    chunks: (async function* () {
      yield 'data: {"type":"error","message":"Provider unavailable"}\n\n';
    })(),
    cancel: jest.fn(),
  }));
  const client = createClient(request, undefined, stream);
  await render(
    <CopilotProvider client={client}>
      <CopilotConversationScreen />
    </CopilotProvider>,
  );
  await waitFor(() => expect(screen.getByLabelText("Message")).toBeEnabled());
  await fireEvent.changeText(
    screen.getByLabelText("Message"),
    "What maintenance is recorded?",
  );
  await fireEvent.press(screen.getByRole("button", { name: "Send message" }));
  await screen.findByText("Provider unavailable");
  expect(screen.getByLabelText("Message").props.value).toBe(
    "What maintenance is recorded?",
  );
  expect(stream).toHaveBeenCalledWith(
    "/api/v1/ai/conversations/chat-one/messages",
    { message: "What maintenance is recorded?" },
  );
  expect(screen.getByRole("button", { name: "Send message" })).toBeEnabled();
});

it("shows saved messages and safely renders model output as text", async () => {
  const request = jest.fn(async () => ({
    data: [
      {
        id: "message",
        role: "assistant",
        content: "<script>untrusted()</script>",
        status: "completed",
        created_at: "2026-10-10",
      },
    ],
    meta: { current_page: 1, last_page: 1 },
  })) as jest.Mock & Request;
  const client = createClient(request);
  await render(
    <CopilotProvider client={client}>
      <CopilotConversationScreen />
    </CopilotProvider>,
  );
  await screen.findByText("<script>untrusted()</script>");
  expect(request).toHaveBeenCalledWith(
    "/api/v1/ai/conversations/chat-one/messages?page=1",
  );
});
