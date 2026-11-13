import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type { Picture } from './pictures';
import { bump, t } from '../util';

export interface StageAttrs extends ComponentAttrs {
  pictures: Picture[];
  /** The cover's box from the server, used when the markup doesn't say. */
  ratio: number;
}

/**
 * The opening post's pictures: one large, the rest as thumbnails beside it
 * (under it on a phone, as dots).
 *
 * The stage keeps one box for every picture, the first picture's shape, and
 * fits each inside it: stepping through them never moves the words below.
 */
export default class Stage extends Component<StageAttrs> {
  index = 0;

  /** Where a touch began, for a swipe between pictures. */
  touchX: number | null = null;

  view(): Mithril.Children {
    const pictures = this.attrs.pictures;
    const count = pictures.length;
    const index = Math.min(this.index, count - 1);
    const current = pictures[index];
    const ratio = Math.max(420, Math.min(1300, pictures[0].ratio || this.attrs.ratio || 750));

    return (
      <div className={classList('StudioStage', { 'StudioStage--single': count === 1 })}>
        {count > 1 ? (
          <div className="StudioStage-thumbs" role="group" aria-label={t('stage.thumbnails')}>
            {pictures.map((picture, i) => (
              <button
                type="button"
                className={classList('StudioStage-thumb', { active: i === index })}
                aria-label={t('stage.show', { number: i + 1, count })}
                aria-pressed={i === index ? 'true' : 'false'}
                onclick={() => {
                  this.index = i;
                  bump();
                }}
              >
                <img src={picture.thumb} alt="" loading="lazy" decoding="async" />
              </button>
            ))}
          </div>
        ) : null}

        <figure
          className="StudioStage-figure"
          style={{ aspectRatio: `1000 / ${ratio}` }}
          tabindex={count > 1 ? 0 : undefined}
          aria-label={count > 1 ? t('stage.label', { number: index + 1, count }) : undefined}
          ontouchstart={(e: TouchEvent) => {
            this.touchX = e.touches.length === 1 ? e.touches[0].clientX : null;
          }}
          ontouchend={(e: TouchEvent) => {
            const start = this.touchX;
            this.touchX = null;

            if (count < 2 || start === null) return;

            const dx = e.changedTouches[0].clientX - start;

            if (Math.abs(dx) > 40) {
              this.step(dx < 0 ? 1 : -1);
              if (e.cancelable) e.preventDefault();
            }
          }}
          onkeydown={(e: KeyboardEvent) => {
            if (e.key === 'ArrowRight') {
              this.step(1);
              e.preventDefault();
            } else if (e.key === 'ArrowLeft') {
              this.step(-1);
              e.preventDefault();
            }
          }}
        >
          <a href={current.src} target="_blank" rel="noopener noreferrer" className="StudioStage-open">
            <img key={current.src} src={current.src} alt={current.alt} decoding="async" fetchpriority={index === 0 ? 'high' : undefined} />
          </a>

          {count > 1 ? (
            <>
              <Button
                className="Button Button--icon StudioStage-step StudioStage-step--prev"
                icon="fas fa-chevron-left"
                aria-label={t('stage.previous')}
                onclick={() => this.step(-1)}
              />
              <Button
                className="Button Button--icon StudioStage-step StudioStage-step--next"
                icon="fas fa-chevron-right"
                aria-label={t('stage.next')}
                onclick={() => this.step(1)}
              />
              <span className="StudioStage-counter" aria-hidden="true">
                {index + 1} / {count}
              </span>
              <span className="StudioStage-dots" aria-hidden="true">
                {pictures.map((_, i) => (
                  <span className={classList('StudioStage-dot', { active: i === index })} />
                ))}
              </span>
            </>
          ) : null}
        </figure>
      </div>
    );
  }

  step(by: number) {
    const count = this.attrs.pictures.length;

    this.index = (this.index + by + count) % count;
    bump();
  }
}
