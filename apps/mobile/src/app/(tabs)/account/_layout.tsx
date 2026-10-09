import { DomainStack } from "@/navigation/domain-stack";
export const unstable_settings = { anchor: "index" };
export default function AccountLayout() {
  return <DomainStack title="Account" home="/account" />;
}
