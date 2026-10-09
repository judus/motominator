import { useEffect, useRef, useState } from "react";
import type { Client } from "../client";
import type { AiSettings } from "../models";
import { failureMessage, fieldErrors } from "../errors";

export function useAiSettings(
  client: Client,
  verified: boolean,
  loadErrorMessage?: (failure: unknown) => string,
) {
  const [settings, setSettings] = useState<AiSettings | null>(null);
  const [provider, setProvider] = useState("openai");
  const [model, setModel] = useState("");
  const [key, setKey] = useState("");
  const [revision, setRevision] = useState(0);
  const [attempt, setAttempt] = useState(0);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const pending = useRef(false);
  const mounted = useRef(false);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  function accept(value: AiSettings) {
    setSettings(value);
    setProvider(value.data?.provider ?? value.providers[0]?.id ?? "openai");
    setModel(value.data?.model ?? "");
    setKey("");
    setRevision((previous) => previous + 1);
  }
  useEffect(() => {
    let active = true;
    client.ai.settings().then(
      (value) => {
        if (active) accept(value);
      },
      (failure) => {
        if (active)
          setError(
            loadErrorMessage?.(failure) ??
              "Unable to load AI settings. Please retry.",
          );
      },
    );
    return () => {
      active = false;
    };
  }, [client, attempt, loadErrorMessage]);
  const changed =
    !!settings?.data &&
    (provider !== settings.data.provider ||
      model !== settings.data.model ||
      !!key);
  const needsKey = !settings?.data || provider !== settings.data.provider;
  async function perform(action: "save" | "test" | "remove") {
    if (
      pending.current ||
      !settings ||
      (action !== "remove" && !verified) ||
      (action === "test" && (!settings.data || changed))
    )
      return;
    pending.current = true;
    setBusy(true);
    setError("");
    setErrors({});
    setMessage("");
    try {
      if (action === "test") {
        const result = await client.ai.test();
        if (mounted.current) setMessage(result.message);
      } else {
        const result =
          action === "save"
            ? await client.ai.save({
                provider,
                model: model.trim(),
                ...(key ? { api_key: key.trim() } : {}),
              })
            : await client.ai.remove();
        if (mounted.current) {
          accept(result);
          setMessage(
            action === "save" ? "AI settings saved." : "AI key removed.",
          );
        }
      }
    } catch (failure) {
      if (mounted.current) {
        setErrors(fieldErrors(failure));
        setError(failureMessage(failure, "Unable to update AI settings."));
      }
    } finally {
      pending.current = false;
      if (mounted.current) setBusy(false);
    }
  }
  return {
    settings,
    provider,
    model,
    key,
    revision,
    busy,
    message,
    error,
    errors,
    changed,
    needsKey,
    perform,
    retry: () => {
      if (!pending.current) {
        setError("");
        setAttempt((value) => value + 1);
      }
    },
    changeProvider: (value: string) => {
      setProvider(value);
      setModel("");
      setKey("");
      setRevision((value) => value + 1);
      setMessage("");
      setErrors({});
    },
    changeModel: (value: string) => {
      setModel(value);
      setMessage("");
    },
    changeKey: (value: string) => {
      setKey(value);
      setMessage("");
    },
  };
}
