import { FlatList, type ListRenderItem } from "react-native";
import { getTokens, useTheme } from "tamagui";
import type { ReactNode } from "react";
import { Column, H2, Text } from "./components";
export function RecordList<T extends { id: number }>({
  title,
  description,
  children,
  data,
  renderItem,
  refreshing,
  onRefresh,
  empty,
  footer,
}: {
  title: string;
  description?: string;
  children?: ReactNode;
  data: T[];
  renderItem: ListRenderItem<T>;
  refreshing: boolean;
  onRefresh: () => void;
  empty: ReactNode;
  footer?: ReactNode;
}) {
  const theme = useTheme(),
    space = getTokens().space;
  return (
    <FlatList
      style={{ flex: 1, backgroundColor: theme.background.val }}
      contentInsetAdjustmentBehavior="automatic"
      contentContainerStyle={{
        padding: space["$4"].val,
        gap: space["$3"].val,
        maxWidth: 1000,
        width: "100%",
        alignSelf: "center",
      }}
      data={data}
      keyExtractor={(item) => String(item.id)}
      renderItem={renderItem}
      refreshing={refreshing}
      onRefresh={onRefresh}
      ListHeaderComponent={
        <Column gap="$4">
          <H2>{title}</H2>
          {description && <Text color="$color11">{description}</Text>}
          {children}
        </Column>
      }
      ListEmptyComponent={<Column gap="$3">{empty}</Column>}
      ListFooterComponent={
        footer ? <Column gap="$3">{footer}</Column> : undefined
      }
    />
  );
}
