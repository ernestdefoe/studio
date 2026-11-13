import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';
import Card from './Card';
import { boxRatio, isPhone, railMode } from '../util';

export interface GalleryAttrs extends ComponentAttrs {
  /** A DiscussionListState: the same list core's DiscussionList draws. */
  state: any;
}

/**
 * The discussion list as a masonry gallery.
 *
 * Columns are real columns, filled in order, each card going to the shortest
 * one. The heights are known before anything loads (the cover's box comes
 * from the server; the words under it are estimated from the title), so the
 * layout is decided once: loading more discussions only appends to the
 * columns, and no card already on screen moves. CSS multi-column would
 * reflow every card on each "Load more".
 *
 * The list, its pages, the loading state and "Load more" are core's own
 * DiscussionListState; only the drawing is different.
 */
export default class Gallery extends Component<GalleryAttrs> {
  /** The width the columns were last laid out for. */
  width = 0;

  observer?: ResizeObserver;

  oninit(vnode: Mithril.Vnode<GalleryAttrs, this>) {
    super.oninit(vnode);

    // A first guess, so the first draw already has about the right columns.
    this.width = Math.max(320, (window.innerWidth || 1280) - (isPhone() ? 24 : 320));
  }

  oncreate(vnode: Mithril.VnodeDOM<GalleryAttrs, this>) {
    super.oncreate(vnode);

    if (typeof ResizeObserver === 'undefined') return;

    this.observer = new ResizeObserver((entries) => {
      const width = Math.round(entries[0]?.contentRect.width || 0);

      if (width && this.layout(width).columns !== this.layout(this.width).columns) {
        this.width = width;
        m.redraw();
      } else if (width) {
        this.width = width;
      }
    });

    this.observer.observe(vnode.dom as Element);
  }

  onremove(vnode: Mithril.VnodeDOM<GalleryAttrs, this>) {
    super.onremove(vnode);
    this.observer?.disconnect();
  }

  /** How many columns, with what gap, for a width. */
  layout(width: number): { columns: number; gap: number } {
    const rail = railMode();
    const gap = isPhone() ? 10 : rail ? 8 : 18;
    const min = isPhone() ? 150 : rail ? 200 : 220;
    const columns = isPhone() ? 2 : Math.max(2, Math.min(rail ? 6 : 5, Math.floor((width + gap) / (min + gap))));

    return { columns, gap };
  }

  /** A card's height, before it is drawn. Only used to choose a column. */
  estimate(discussion: Discussion, column: number, tile: boolean): number {
    const cover = discussion.attribute<string | null>('studioCover');
    const picture = cover ? (column * boxRatio(discussion.attribute<number>('studioRatio'))) / 1000 : column * 0.75;

    if (tile) return picture;

    // Words under the picture: the avatar row, up to three lines of title, the stats.
    const perLine = Math.max(10, (column - 28) / 8.4);
    const lines = Math.min(3, Math.ceil(discussion.title().length / perLine));

    return picture + 44 + lines * 21 + 30;
  }

  view(): Mithril.Children {
    const state = this.attrs.state;

    if (state.isEmpty()) {
      return (
        <div className="DiscussionList StudioGallery">
          <Placeholder text={app.translator.trans('core.forum.discussion_list.empty_text')} />
        </div>
      );
    }

    const tile = railMode();
    const { columns, gap } = this.layout(this.width);
    // 🚨 Estimated at a fixed nominal width, not the measured one: the
    // distribution must depend on the column count alone, or the first
    // redraw after measuring (or Load more) would deal the cards again.
    const column = isPhone() ? 180 : 260;
    const heights = new Array(columns).fill(0);
    const cols: Discussion[][] = Array.from({ length: columns }, () => []);

    state.getPages().forEach((page: any) =>
      page.items.forEach((discussion: Discussion) => {
        const shortest = heights.indexOf(Math.min(...heights));

        cols[shortest].push(discussion);
        heights[shortest] += this.estimate(discussion, column, tile) + gap;
      })
    );

    const isLoading = state.isInitialLoading() || state.isLoadingNext();
    let more: Mithril.Children = null;

    if (isLoading) {
      more = <LoadingIndicator />;
    } else if (state.hasNext()) {
      more = (
        <Button className="Button" onclick={state.loadNext.bind(state)}>
          {app.translator.trans('core.forum.discussion_list.load_more_button')}
        </Button>
      );
    }

    return (
      <div className="DiscussionList StudioGallery">
        <div className="StudioGallery-grid" role="feed" aria-busy={isLoading} style={{ '--studio-gap': `${gap}px` }}>
          {cols.map((items, i) => (
            <div className="StudioGallery-column" key={i}>
              {items.map((discussion) => (
                <div className="StudioGallery-item" key={discussion.id()} data-id={discussion.id()} role="article">
                  <Card discussion={discussion} tile={tile} />
                </div>
              ))}
            </div>
          ))}
        </div>
        <div className="DiscussionList-loadMore">{more}</div>
      </div>
    );
  }
}
