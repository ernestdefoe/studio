import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import listItems from 'flarum/common/helpers/listItems';
import classList from 'flarum/common/utils/classList';
import username from 'flarum/common/helpers/username';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';
import { boxRatio, flagOf, mediumOf, nearFor, t } from '../util';

export interface CardAttrs extends ComponentAttrs {
  discussion: Discussion;
  /** Dark gallery mode: a tile, its words over the picture. */
  tile: boolean;
}

/**
 * One discussion in the gallery: its own first picture in a box of the
 * picture's shape (so nothing moves as it loads), the creator's avatar
 * overlapping it, the title, and replies.
 *
 * A discussion without a picture is a typographic card: its title set large
 * on its medium's colour.
 */
export default class Card extends Component<CardAttrs> {
  view(): Mithril.Children {
    const discussion = this.attrs.discussion;
    const cover = discussion.attribute<string | null>('studioCover');
    const href = app.route.discussion(discussion, nearFor(discussion));
    const user = discussion.user();
    const medium = mediumOf(discussion);
    const flag = flagOf(discussion);
    const count = discussion.attribute<number>('studioImages') || 0;
    const tint = medium?.color() || null;

    return (
      <article
        className={classList('StudioCard', {
          'StudioCard--type': !cover,
          'StudioCard--tile': this.attrs.tile,
          'is-unread': discussion.isUnread(),
          'is-sticky': discussion.attribute('isSticky'),
        })}
        style={tint ? { '--studio-tint': tint } : undefined}
      >
        <Link href={href} className="StudioCard-media" tabindex="-1" aria-hidden="true">
          {cover ? (
            <span className="StudioCard-box" style={{ aspectRatio: `1000 / ${boxRatio(discussion.attribute<number>('studioRatio'))}` }}>
              <img src={cover} alt="" loading="lazy" decoding="async" />
            </span>
          ) : (
            <span className="StudioCard-box StudioCard-box--type">
              <span className="StudioCard-typeTitle">{discussion.title()}</span>
            </span>
          )}
          {flag ? <span className="StudioCard-flag">{flag.name()}</span> : null}
          {count > 1 ? (
            <span className="StudioCard-count" title={t('card.pictures', { count })}>
              <Icon name="far fa-images" /> {count}
            </span>
          ) : null}
        </Link>

        <div className="StudioCard-meta">
          <div className="StudioCard-who">
            {user ? (
              <Link href={app.route.user(user)} className="StudioCard-avatar" aria-label={user.displayName()}>
                <Avatar user={user} />
              </Link>
            ) : (
              <span className="StudioCard-avatar">
                <Avatar user={null} />
              </span>
            )}
            <span className="StudioCard-name">{username(user)}</span>
            {medium && !this.attrs.tile ? <span className="StudioCard-medium">{medium.name()}</span> : null}
          </div>

          <h3 className="StudioCard-title">
            <Link href={href}>{discussion.title()}</Link>
          </h3>

          {this.statsView()}
        </div>
      </article>
    );
  }

  statsView(): Mithril.Children {
    const discussion = this.attrs.discussion;
    const items: Mithril.Children[] = [];
    const badges = discussion.badges().toArray();

    if (badges.length) {
      items.push(<ul className="StudioCard-badges">{listItems(badges)}</ul>);
    }

    if (app.forum.attribute('studioCounts')) {
      const replies = Math.max(0, (discussion.commentCount() || 1) - 1);
      const views = discussion.attribute<number | undefined>('views');

      items.push(<span className="StudioCard-stat">{t('card.replies', { count: replies })}</span>);

      if (typeof views === 'number') {
        items.push(<span className="StudioCard-stat">{t('card.views', { count: views })}</span>);
      }
    }

    if (discussion.isUnread()) {
      items.push(<span className="StudioCard-unread">{t('card.new')}</span>);
    }

    return items.length ? <div className="StudioCard-stats">{items}</div> : null;
  }
}
