import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import username from 'flarum/common/helpers/username';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';
import { t } from '../util';

export interface MoreFromAttrs extends ComponentAttrs {
  discussion: Discussion;
}

/** One request per creator per visit, however many of their works are opened. */
const asked = new Map<string, Promise<Discussion[]>>();

/**
 * A strip of the creator's other works with pictures. One request, made once
 * the card is drawn, for the creator's six newest discussions; works without
 * a picture are left out of the strip.
 */
export default class MoreFrom extends Component<MoreFromAttrs> {
  works: Discussion[] | null = null;

  oncreate(vnode: Mithril.VnodeDOM<MoreFromAttrs, this>) {
    super.oncreate(vnode);

    const user = this.attrs.discussion.user();

    if (!user) return;

    const key = String(user.id());

    if (!asked.has(key)) {
      asked.set(
        key,
        app.store
          .find<Discussion[]>('discussions', { filter: { author: user.username() }, page: { limit: 7 }, include: 'user' }, undefined, {
            errorHandler: () => {},
          })
          .then((list) => [...list])
          .catch(() => [])
      );
    }

    asked.get(key)!.then((list) => {
      this.works = list;
      m.redraw();
    });
  }

  view(): Mithril.Children {
    const current = this.attrs.discussion;
    const user = current.user();
    const works = (this.works || []).filter((d) => d !== current && d.attribute('studioCover')).slice(0, 6);

    if (!user || !works.length) return <div className="StudioMore StudioMore--empty" />;

    return (
      <div className="StudioMore">
        <h2 className="StudioMore-title">{t('more.title', { name: username(user) })}</h2>
        <ul className="StudioMore-grid">
          {works.map((work) => (
            <li>
              <Link href={app.route.discussion(work)} className="StudioMore-item" title={work.title()}>
                <img src={work.attribute<string>('studioCover')} alt={work.title()} loading="lazy" decoding="async" />
              </Link>
            </li>
          ))}
        </ul>
      </div>
    );
  }
}
