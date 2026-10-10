import { useCallback, useEffect, useState } from "react";
import type { Page } from "../models";
import { failureMessage } from "../errors";

export function usePage<T>(load: (page: number) => Promise<Page<T>>) {
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const reload = useCallback(() => setRevision((value) => value + 1), []);
  const key = `${page}:${revision}`;
  const [state, setState] = useState<{
    source?: typeof load;
    key: string;
    result?: Page<T>;
    error?: string;
  }>({ key: "" });
  useEffect(() => {
    let active = true;
    load(page).then(
      (result) => {
        if (active) setState({ source: load, key, result });
      },
      (failure) => {
        if (active)
          setState((previous) => ({
            source: load,
            key,
            result: previous.source === load ? previous.result : undefined,
            error: failureMessage(failure, "Unable to load records."),
          }));
      },
    );
    return () => {
      active = false;
    };
  }, [load, page, key]);
  const sameSource = state.source === load;
  return {
    result:
      sameSource && state.result?.meta.current_page === page
        ? state.result
        : undefined,
    error: sameSource && state.key === key ? state.error : undefined,
    loading: !sameSource || state.key !== key,
    page,
    setPage,
    reload,
  };
}
