import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import username from 'flarum/common/helpers/username';
import extractText from 'flarum/common/utils/extractText';
import classList from 'flarum/common/utils/classList';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';
import MoreFrom from './MoreFrom';
import { bump, has, mediumOf, t } from '../util';

export interface CreatorAttrs extends ComponentAttrs {
  discussion: Discussion;
  /**
   * `side`: the whole card beside the posts (wide screens). On phones it is
   * split around the post: `inline` (who and what) above the words, `facts`
   * (the spec sheet and Appreciate) below them.
   */
  variant: 'side' | 'inline' | 'facts';
}

/** The opening post, when the stream has it loaded. */
export function openingPost(discussion: Discussion): any | null {
  return app.store.all<any>('posts').find((post) => post.discussion?.() === discussion && post.number() === 1) || null;
}

/**
 * Who made it, what it is, and the facts about it: the creator with Follow
 * (ianm/follow-users), the discussion's own hero (its title, tags and
 * whatever other extensions add there), a spec sheet, Appreciate (a like on
 * the opening post, flarum/likes), and the creator's builds, projects and
 * gallery when Garage, Projects or Atrium is installed.
 */
export default class Creator extends Component<CreatorAttrs> {
  view(): Mithril.Children {
    const discussion = this.attrs.discussion;
    const user = discussion.user();
    const side = this.attrs.variant === 'side';

    if (this.attrs.variant === 'facts') {
      return (
        <section className="StudioCreator StudioCreator--facts" aria-label={t('spec.label')}>
          {this.specView()}
          {this.actionsView()}
          {this.linksView()}
        </section>
      );
    }

    return (
      <section className={classList('StudioCreator', `StudioCreator--${this.attrs.variant}`)} aria-label={t('creator.label')}>
        <div className="StudioCreator-who">
          {user ? (
            <Link href={app.route.user(user)} className="StudioCreator-avatar" tabindex="-1" aria-hidden="true">
              <Avatar user={user} />
            </Link>
          ) : (
            <span className="StudioCreator-avatar">
              <Avatar user={null} />
            </span>
          )}
          <div className="StudioCreator-name">
            {user ? <Link href={app.route.user(user)}>{username(user)}</Link> : username(user)}
            {user ? <div className="StudioCreator-facts">{this.facts(user)}</div> : null}
          </div>
          {!side ? this.followButton() : null}
        </div>

        {side ? this.followButton() : null}

        <DiscussionHero discussion={discussion} />

        {side ? this.specView() : null}
        {side ? this.actionsView() : null}
        {side ? this.linksView() : null}
        {side && user ? <MoreFrom discussion={discussion} /> : null}
      </section>
    );
  }

  facts(user: any): string {
    const parts: string[] = [];
    const works = user.discussionCount?.();
    const followers = user.attribute('followerCount');

    if (typeof works === 'number') parts.push(extractText(t('creator.works', { count: works })));
    if (typeof followers === 'number') parts.push(extractText(t('creator.followers', { count: followers })));

    return parts.join(' · ');
  }

  followButton(): Mithril.Children {
    const user = this.attrs.discussion.user();
    const me = app.session.user;

    if (!user || !has('ianm-follow-users') || user === me) return null;

    const followed = !!user.attribute('followed');

    if (me && !followed && !user.attribute('canBeFollowed')) return null;

    return (
      <Button
        className={classList('Button StudioCreator-follow', { 'Button--primary': !followed })}
        icon={followed ? 'fas fa-check' : 'fas fa-user-plus'}
        aria-pressed={followed ? 'true' : 'false'}
        onclick={() => {
          if (!me) {
            app.modal.show(() => import('flarum/forum/components/LogInModal'));
            return;
          }

          user.save({ followUsers: followed ? null : 'follow' }).then(bump, bump);
        }}
      >
        {followed ? t('creator.following') : t('creator.follow', { name: username(user) })}
      </Button>
    );
  }

  /** The spec sheet: the facts a reader of a work wants, from what the forum knows. */
  specItems(): [Mithril.Children, Mithril.Children][] {
    const discussion = this.attrs.discussion;
    const medium = mediumOf(discussion);
    const pictures = discussion.attribute<number>('studioImages') || 0;
    const views = discussion.attribute<number | undefined>('views');
    const rows: [Mithril.Children, Mithril.Children][] = [];

    if (medium) rows.push([t('spec.medium'), medium.name()]);
    if (pictures) rows.push([t('spec.pictures'), String(pictures)]);
    rows.push([t('spec.posted'), <time dateTime={discussion.createdAt()?.toISOString()}>{dayjs(discussion.createdAt()).format('LL')}</time>]);
    rows.push([t('spec.replies'), String(Math.max(0, (discussion.commentCount() || 1) - 1))]);
    if (typeof views === 'number') rows.push([t('spec.views'), String(views)]);

    return rows;
  }

  specView(): Mithril.Children {
    return (
      <dl className="StudioSpec">
        {this.specItems().map(([label, value]) => (
          <div className="StudioSpec-row">
            <dt>{label}</dt>
            <dd>{value}</dd>
          </div>
        ))}
      </dl>
    );
  }

  actionsView(): Mithril.Children {
    const post = openingPost(this.attrs.discussion);

    if (!post || !has('flarum-likes') || typeof post.canLike !== 'function' || post.isHidden()) return null;

    const me = app.session.user;
    const liked = !!me && (post.likes?.() || []).some((u: any) => u === me);
    const count = post.likesCount?.() || 0;

    if (!post.canLike() && !liked) {
      return count ? <div className="StudioCreator-likes">{t('creator.appreciated', { count })}</div> : null;
    }

    return (
      <Button
        className={classList('Button StudioCreator-appreciate', { 'Button--primary': !liked, active: liked })}
        icon={liked ? 'fas fa-heart' : 'far fa-heart'}
        aria-pressed={liked ? 'true' : 'false'}
        onclick={() => {
          const like = !liked;

          post.save({ isLiked: like }).then(bump, bump);

          // As flarum/likes does: show it at once, the response fills in the rest.
          const relationships = (post.data.relationships ??= {});
          const data = (relationships.likes ??= { data: [] }).data;
          const i = data.findIndex((entry: any) => entry.id === me!.id());

          if (i >= 0) data.splice(i, 1);
          if (like) data.unshift({ type: 'users', id: me!.id() });
          post.pushAttributes({ likesCount: Math.max(0, count + (like ? 1 : -1)) });
          bump();
        }}
      >
        {t('creator.appreciate', { count })}
      </Button>
    );
  }

  /** The creator's other homes on this forum, from the extensions that have them. */
  linksView(): Mithril.Children {
    const user = this.attrs.discussion.user();

    if (!user) return null;

    const links = [
      ['user.garage', 'creator.builds', 'fas fa-wrench'],
      ['user.projects', 'creator.projects', 'fas fa-folder-open'],
      ['atrium.user', 'creator.gallery', 'far fa-images'],
    ].filter(([route]) => app.routes[route]);

    if (!links.length) return null;

    return (
      <nav className="StudioCreator-links" aria-label={t('creator.links_label', { name: username(user) })}>
        {links.map(([route, key, icon]) => (
          <Link className="Button Button--flat StudioCreator-link" href={app.route(route, { username: user.slug() })}>
            <i className={`icon ${icon}`} aria-hidden="true" /> {t(key)}
          </Link>
        ))}
      </nav>
    );
  }
}
