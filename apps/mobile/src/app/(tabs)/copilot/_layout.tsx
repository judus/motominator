import { DomainStack } from "@/navigation/domain-stack";
export const unstable_settings = { anchor: "index" };
export default function CopilotLayout() {
  return <DomainStack title="Copilot" home="/copilot" />;
}
