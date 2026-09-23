import { toRef } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { useAjax } from '@/composables/useAjax';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { usePane } from '@/composables/usePane';
import ActivityLogDetailPane from '@/components/forms/activity_logs/ActivityLogDetailPane.vue';
import ActivityLogEntryDetails from '@/components/forms/activity_logs/ActivityLogEntryDetails.vue';

/** One row of the audit trail, as `ActivityLogListResource` shapes it. */
export interface ActivityLogRow {
  id: number;
  logged_at: string | null;
  logged_at_value: string | null;
  log_name: string;
  module: string;
  event: string | null;
  event_label: string | null;
  description: string | null;
  subject_type: string | null;
  subject_id: number | string | null;
  causer: string | null;
  causer_id: number | null;
  batch_uuid: string | null;
  change_count: number;
}

/** One field that changed, already rendered for display by the server. */
export interface ActivityLogChange {
  field: string;
  label: string;
  from: string | null;
  to: string | null;
}

/** The request an entry was written on, when it came from one. */
export interface ActivityLogContext {
  ip?: string;
  user_agent?: string;
  route?: string;
  method?: string;
}

/** A single entry opened up, as `ActivityLogDetailResource` shapes it. */
export interface ActivityLogDetail extends ActivityLogRow {
  causer_email: string | null;
  changes: ActivityLogChange[];
  context: ActivityLogContext | null;
  /** How many other entries the same action wrote; the entries themselves are paged. */
  batch_sibling_count: number;
}

/** A page of rows, as `CommonResource` envelopes a paginator. */
export interface ActivityLogPage {
  data: ActivityLogRow[];
  current_page: number;
  per_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

/** What a page asks for. Matches `ListRequest`, which validates it. */
export interface ActivityLogPageParams {
  page?: number;
  per_page?: number;
}

/**
 * Event colours, kept beside the rows that use them rather than in the enum: which
 * hue reads as "deleted" is a presentation decision, not a property of the event.
 */
const EVENT_CLASSES: Record<string, string> = {
  created: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  deleted: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  restored: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
};

/** The badge classes for an event, wherever one is shown — table, pane or batch list. */
export const eventClass = (event: string | null | undefined): string =>
  EVENT_CLASSES[event ?? ''] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';

export function useActivityLogs() {
  const { slug } = useModulePermissions();
  const { get } = useAjax();
  const {
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
    rightPane,
    topPane,
  } = usePane();

  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

  const topPaneVisible = toRef(topPane, 'open');
  const topPaneTitle = toRef(topPane, 'title');
  const topPaneLoading = toRef(topPane, 'loading');
  const topPaneError = toRef(topPane, 'error');
  const topPaneContentComponent = toRef(topPane, 'contentComponent');
  const topPaneComponentProps = toRef(topPane, 'componentProps');

  /** The title a pane carries for an entry — the same wording wherever it is opened. */
  const paneTitle = (row: ActivityLogRow): string =>
    `${row.module} · ${row.event_label ?? 'Activity'}`;

  /**
   * Fetch one entry with its field-by-field changes.
   *
   * The listing carries no snapshots, so the detail is pulled only for the entry
   * actually opened.
   */
  const getActivityLog = async (id: number | string): Promise<ActivityLogDetail | null> => {
    try {
      const response = await get<{ activity_log: ActivityLogDetail }>(`/${slug.value}/${id}/show`);

      if (!response.ok) {
        throw new Error('Failed to fetch the activity log entry');
      }

      return response.data?.activity_log ?? null;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });

      return null;
    }
  };

  /**
   * Fetch one page of the rest of the action an entry belongs to.
   *
   * A batch upload writes an entry per row of the file, so the batch is paged like
   * any other listing rather than travelling with the entry that opened it.
   */
  const getBatchSiblings = async (
    id: number | string,
    params: ActivityLogPageParams = {},
  ): Promise<ActivityLogPage | null> => {
    const query: Record<string, string | number> = {};

    if (params.page) query.page = params.page;
    if (params.per_page) query.per_page = params.per_page;

    try {
      const response = await get<{ batch_siblings: ActivityLogPage }>(
        `/${slug.value}/${id}/batch_siblings`,
        query,
      );

      if (!response.ok) {
        throw new Error('Failed to fetch the rest of the action');
      }

      return response.data?.batch_siblings ?? null;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });

      return null;
    }
  };

  /**
   * Open an entry in the right pane.
   *
   * The row is passed through as well, so the pane has something to show immediately
   * and while the detail is on its way.
   */
  const openActivityLog = async (row: ActivityLogRow) => {
    showLoader();

    try {
      const detail = await getActivityLog(row.id);

      openPane({
        side: 'right',
        title: paneTitle(row),
        component: ActivityLogDetailPane,
        componentProps: { row, detail },
      });
    } catch {
      setPaneLoading('right', false);
      setPaneError('right', 'Error fetching the activity log entry.');
      setPaneContent('right', null);
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  /**
   * Open one entry's changes in the top pane, over whatever opened it.
   *
   * Reading a batch means dipping into entry after entry, so a sibling opens above
   * the pane holding the list instead of replacing it: closing the top pane puts the
   * reader back on the same page of the same batch.
   */
  const openActivityLogChanges = async (row: ActivityLogRow) => {
    showLoader();

    try {
      const detail = await getActivityLog(row.id);

      openPane({
        side: 'top',
        title: paneTitle(row),
        component: ActivityLogEntryDetails,
        componentProps: { row, detail },
      });
    } catch {
      setPaneLoading('top', false);
      setPaneError('top', 'Error fetching the activity log entry.');
      setPaneContent('top', null);
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  return {
    getActivityLog,
    getBatchSiblings,
    openActivityLog,
    openActivityLogChanges,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
    topPaneVisible,
    topPaneTitle,
    topPaneLoading,
    topPaneError,
    topPaneContentComponent,
    topPaneComponentProps,
  };
}
