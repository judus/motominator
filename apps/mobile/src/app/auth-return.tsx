import { Redirect, useLocalSearchParams } from "expo-router";

export default function AuthReturn() {
  const { link_code } = useLocalSearchParams();
  return (
    <Redirect href={typeof link_code === "string" ? "/account/social" : "/"} />
  );
}
