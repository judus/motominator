import { useCallback, useRef } from "react";
import { useFocusEffect } from "expo-router";
/** Stack screens remain mounted; revalidate when returning, after the initial load. */
export function useRefreshOnFocus(reload: () => void) {
  const visited = useRef(false);
  useFocusEffect(
    useCallback(() => {
      if (visited.current) reload();
      visited.current = true;
    }, [reload]),
  );
}
