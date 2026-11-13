import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import classList from 'flarum/common/utils/classList';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import Gallery from './Gallery';
import { isDark, t } from '../util';

/** The forum's top-level tags, in the order the forum set: the medium chips. */
function mediums(): any[] {
  if (!app.store.all) return [];

  return app.store
    .all<any>('tags')
    .filter((tag) => tag.position() !== null && !tag.parent?.())
    .sort((a, b) => a.position() - b.position())
    .slice(0, 12);
}

/**
 * Dark mode is the gallery mode. The switch saves the choice like core's own
 * theme switcher: a member's preference, a guest's session.
 */
function setScheme(scheme: string) {
  app.setColorScheme(scheme);

  if (app.session.user) {
    app.session.user.savePreferences({ colorScheme: scheme });
  } else {
    try {
      sessionStorage.setItem('colorScheme', scheme);
    } catch (e) {
      // Storage is blocked: the scheme holds until the page is left.
    }
  }

  m.redraw();
}

export default function gallery() {
  // The list, drawn as the gallery.
  extend(IndexPage.prototype, 'contentItems', function (items: ItemList<Mithril.Children>) {
    if (items.has('discussionList')) {
      items.setContent('discussionList', <Gallery state={app.discussions} />);
    }
  });

  // A member's own page of discussions is their portfolio. 🚨 By module
  // name: user pages are lazy chunks in Flarum 2, not loaded at boot.
  override('flarum/forum/components/DiscussionsUserPage', 'content', function () {
    return (
      <div className="DiscussionsUserPage">
        <Gallery state={(this as any).state} />
      </div>
    );
  });

  // A chip per medium above the gallery, then the sort and the actions.
  extend(IndexPage.prototype, 'toolbarItems', function (items: ItemList<Mithril.Children>) {
    if (!app.forum.attribute('studioChips')) return;

    const tags = mediums();

    if (!tags.length) return;

    const current = m.route.param('tags');
    const params = app.search.state.stickyParams();

    items.add(
      'studioChips',
      <nav className="StudioChips" aria-label={t('chips.label')}>
        <Link className={classList('StudioChip', { active: !current })} href={app.route('index', params)}>
          {t('chips.everything')}
        </Link>
        {tags.map((tag) => (
          <Link
            className={classList('StudioChip', { active: current === tag.slug() })}
            href={app.route('tag', { ...params, tags: tag.slug() })}
            style={tag.color() ? { '--studio-tint': tag.color() } : undefined}
          >
            {tag.name()}
          </Link>
        ))}
      </nav>,
      200
    );
  });

  // Gallery mode on and off.
  extend(IndexPage.prototype, 'actionItems', function (items: ItemList<Mithril.Children>) {
    if (!app.forum.attribute('studioToggle')) return;

    const dark = isDark();

    items.add(
      'studioMode',
      <Button
        className="Button StudioMode"
        icon={dark ? 'far fa-sun' : 'fas fa-images'}
        aria-pressed={dark ? 'true' : 'false'}
        aria-label={t(dark ? 'mode.light' : 'mode.gallery')}
        onclick={() => setScheme(dark ? 'light' : 'dark')}
      >
        {t(dark ? 'mode.light' : 'mode.gallery')}
      </Button>,
      120
    );
  });

  // "Share your work".
  extend(IndexSidebar.prototype, 'items', function (items: ItemList<Mithril.Children>) {
    if (!items.has('newDiscussion')) return;

    const button = items.get('newDiscussion') as any;

    // Core's own test: a guest is offered the button and asked to log in.
    if (button?.attrs && (app.forum.attribute('canStartDiscussion') || !app.session.user)) {
      button.attrs.icon = 'fas fa-plus';
      button.attrs['aria-label'] = t('share');
      button.children = [t('share')];
    }
  });
}
