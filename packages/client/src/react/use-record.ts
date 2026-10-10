import { useCallback, useEffect, useState } from "react";
import { failureMessage } from "../errors";

/** Loads one record; old requests cannot replace a new identity or refresh. */
export function useRecord<T>(load: () => Promise<T>) {
  const [revision, setRevision] = useState(0);
  const reload = useCallback(() => setRevision((value) => value + 1), []);
  const [state, setState] = useState<{
    source?: typeof load;
    revision: number;
    result?: T;
    error?: string;
  }>({ revision: -1 });
  useEffect(() => {
    let active = true;
    load().then(
      (result) => {
        if (active) setState({ source: load, revision, result });
      },
      (error) => {
        if (active)
          setState({
            source: load,
            revision,
            error: failureMessage(error, "Unable to load this record."),
          });
      },
    );
    return () => {
      active = false;
    };
  }, [load, revision]);
  const current = state.source === load && state.revision === revision;
  return {
    result: current ? state.result : undefined,
    error: current ? state.error : undefined,
    loading: !current,
    reload,
  };
}
