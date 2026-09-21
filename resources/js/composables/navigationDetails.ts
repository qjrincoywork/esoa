import { toRef } from 'vue';
import { usePane } from '@/composables/usePane';
import NavigationDetailsPane from '@/components/forms/navigations/NavigationDetailsPane.vue';

/** One row of the navigations listing, as `Navigation::getNavigations()` shapes it. */
export interface NavigationRow {
  id: number;
  name: string;
  label?: string | null;
  icon?: string | null;
  status: number;
  order_number?: number | null;
  created_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
  deleted_at?: string | null;
  /** Active modules, eager-loaded by the listing — used only as an up-front hint for the Modules tab's count. */
  modules?: unknown[];
  [key: string]: any;
}

/**
 * Opening one row of the navigations listing into the details pane.
 *
 * The row already carries everything the Details tab shows, so the pane opens
 * immediately — no round trip needed just to read what the listing already fetched.
 * The Modules tab fetches its own (full CRUD) list only once it is first opened.
 */
export function useNavigationDetails() {
  const { openPane, closePane, rightPane } = usePane();

  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

  const openNavigationRow = (navigation: NavigationRow) => {
    openPane({
      side: 'right',
      title: navigation.name,
      component: NavigationDetailsPane,
      componentProps: { navigation },
    });
  };

  return {
    openNavigationRow,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
  };
}
