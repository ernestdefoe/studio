import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import CommentPost from 'flarum/forum/components/CommentPost';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import Creator from './Creator';
import Stage from './Stage';
import { picturesIn } from './pictures';
import { studioTick, t } from '../util';

/** Is this post the work itself: a discussion's opening post, shown with its discussion? */
function isWork(post: any): boolean {
  return post.number() === 1 && post.contentType() === 'comment' && !post.isHidden() && !!post.discussion?.();
}

export default function work() {
  // The card beside the posts. Above core's own Reply button and scrubber.
  extend(DiscussionPage.prototype, 'sidebarItems', function (this: any, items: ItemList<Mithril.Children>) {
    if (this.discussion) {
      items.add('studioCreator', <Creator discussion={this.discussion} variant="side" />, 150);
    }
  });

  // The title lives in the card (and, on a phone, under the pictures).
  override(DiscussionPage.prototype, 'hero', function () {
    return null;
  });

  // Redraw the opening post when the stage or the card in it changes.
  extend(CommentPost.prototype, 'oninit', function (this: any) {
    const post = this.attrs.post;

    this.subtree.check(() => (isWork(post) ? [studioTick(), post.discussion()?.commentCount()].join() : ''));
  });

  extend(CommentPost.prototype, 'classes', function (this: any, classes: string[]) {
    const post = this.attrs.post;

    if (isWork(post)) {
      classes.push('StudioWork');

      if (picturesIn(post.contentHtml()).length) classes.push('StudioWork--pictures');
    }
  });

  // The opening post: its pictures on the stage, then (on a phone) who made
  // it, the words, the spec sheet, and the replies' heading.
  override(CommentPost.prototype, 'content', function (this: any, original: () => Mithril.Children[]) {
    const content = original();
    const post = this.attrs.post;

    if (!isWork(post) || this.isEditing()) return content;

    const discussion = post.discussion();
    const pictures = picturesIn(post.contentHtml());
    const replies = Math.max(0, (discussion.commentCount() || 1) - 1);

    return [
      pictures.length ? <Stage pictures={pictures} ratio={discussion.attribute('studioRatio') || 0} /> : null,
      <Creator discussion={discussion} variant="inline" />,
      ...(content as Mithril.Children[]),
      <Creator discussion={discussion} variant="facts" />,
      <h2 className="StudioWork-replies">{t('work.replies', { count: replies })}</h2>,
    ];
  });
}
