const PARENT_MODULE_BASE = 'form';
const MODULE_BASE = 'input-calendar';

const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;
const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;
const ATTRIBUTE_INPUT_FORMATTED = `${ATTRIBUTE_INPUT}-formatted`;
const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;
const ATTRIBUTE_CONTROL_ACTIVE = `${ATTRIBUTE_CONTROL}-active`;
const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;

const ATTRIBUTE_TABLE = `${ATTRIBUTE_BASE}-table`;
const ATTRIBUTE_HEAD = `${ATTRIBUTE_BASE}-head`;

const EVENT_CHANGE_DATE_BEFORE = `welpodron.${PARENT_MODULE_BASE}.${MODULE_BASE}:changeDate:before`;
const EVENT_CHANGE_DATE_AFTER = `welpodron.${PARENT_MODULE_BASE}.${MODULE_BASE}:changeDate:after`;

type CalendarHeadConfigType = {};

type CalendarHeadPropsType = {
  calendar: Calendar;
  config?: CalendarHeadConfigType;
};

class CalendarHead {
  calendar: Calendar;

  constructor({ calendar, config = {} }: CalendarHeadPropsType) {
    this.calendar = calendar;
  }

  render = () => {
    const div = document.createElement('div');
    div.setAttribute(ATTRIBUTE_HEAD, '');

    let button = document.createElement('button');

    const previousDate = new Date(
      this.calendar.activeDate.getFullYear(),
      this.calendar.activeDate.getMonth(),
      1
    );

    previousDate.setDate(previousDate.getDate() - 1);

    button.innerText = '<';
    button.setAttribute('type', 'button');

    if (this.calendar.currentDate.getTime() > previousDate.getTime()) {
      button.setAttribute('disabled', '');
    } else {
      button.setAttribute(ATTRIBUTE_ACTION_ARGS, '-1');
      button.setAttribute(ATTRIBUTE_ACTION, 'changeMonth');
      button.setAttribute(ATTRIBUTE_CONTROL, '');
      button.setAttribute(
        ATTRIBUTE_BASE_ID,
        this.calendar.element.getAttribute(`${ATTRIBUTE_BASE_ID}`) as string
      );
    }

    div.appendChild(button);

    const p = document.createElement('p');

    let span = document.createElement('span');

    span.innerText = this.calendar.activeDate.toLocaleString('ru-RU', {
      month: 'long',
    });

    p.appendChild(span);

    span = document.createElement('span');

    span.innerText = this.calendar.activeDate.getFullYear().toString();

    p.appendChild(span);

    div.appendChild(p);

    button = document.createElement('button');
    button.setAttribute('type', 'button');
    button.setAttribute(ATTRIBUTE_ACTION_ARGS, '1');
    button.setAttribute(ATTRIBUTE_ACTION, 'changeMonth');
    button.setAttribute(ATTRIBUTE_CONTROL, '');
    button.setAttribute(
      ATTRIBUTE_BASE_ID,
      this.calendar.element.getAttribute(`${ATTRIBUTE_BASE_ID}`) as string
    );
    button.innerText = '>';

    div.appendChild(button);

    return div;
  };
}

type CalendarTableConfigType = {};

type CalendarTablePropsType = {
  calendar: Calendar;
  config?: CalendarTableConfigType;
};

class CalendarTable {
  calendar: Calendar;

  constructor({ calendar, config = {} }: CalendarTablePropsType) {
    this.calendar = calendar;
  }

  render = () => {
    const table = document.createElement('table');
    table.setAttribute(ATTRIBUTE_TABLE, '');

    const thead = document.createElement('thead');

    const tr = document.createElement('tr');

    ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'].forEach((day) => {
      const th = document.createElement('th');

      th.innerText = day;

      tr.appendChild(th);
    });

    thead.appendChild(tr);

    table.appendChild(thead);

    const tbody = document.createElement('tbody');

    this.calendar
      .getPanel({
        year: this.calendar.activeDate.getFullYear(),
        month: this.calendar.activeDate.getMonth(),
      })
      .forEach((week) => {
        const tr = document.createElement('tr');

        week.forEach((day) => {
          const td = document.createElement('td');

          const dayFormatted =
            day.getDate() < 10 ? `0${day.getDate()}` : day.getDate().toString();

          const monthFormatted =
            day.getMonth() + 1 < 10
              ? `0${day.getMonth() + 1}`
              : (day.getMonth() + 1).toString();

          if (this.calendar.activeDate.getMonth() === day.getMonth()) {
            const button = document.createElement('button');

            if (this.calendar.currentDate.getTime() > day.getTime()) {
              button.setAttribute('disabled', '');
            } else {
              button.setAttribute(
                ATTRIBUTE_ACTION_ARGS,
                `${day.getFullYear()}-${monthFormatted}-${dayFormatted}`
              );
              button.setAttribute(ATTRIBUTE_ACTION, 'changeDate');
              button.setAttribute(ATTRIBUTE_CONTROL, '');
              button.setAttribute(
                ATTRIBUTE_BASE_ID,
                this.calendar.element.getAttribute(
                  `${ATTRIBUTE_BASE_ID}`
                ) as string
              );
            }

            button.setAttribute('type', 'button');
            button.innerText = dayFormatted;

            td.appendChild(button);
          }

          tr.appendChild(td);
        });

        tbody.appendChild(tr);
      });

    table.appendChild(tbody);

    return table;
  };
}

type CalendarConfigType = {};

type CalendarPropsType = {
  element: HTMLElement;
  config?: CalendarConfigType;
};

class Calendar {
  element: HTMLElement;

  head: CalendarHead;
  table: CalendarTable;

  supportedActions = ['changeMonth', 'changeDate'];

  // Текущая дата глобально (сегодня)
  currentDate: Date;
  // Сохраненная дата (при клике на дату) (значение инпута)
  savedDate: Date | null = null;
  // Активная дата (при изменении месяца, года)
  activeDate: Date;

  constructor({ element, config = {} }: CalendarPropsType) {
    const date = new Date();
    date.setHours(0, 0, 0, 0);

    this.currentDate = date;
    this.activeDate = new Date(date);

    this.element = element;

    this.head = new CalendarHead({ calendar: this });
    this.table = new CalendarTable({ calendar: this });

    this.element.appendChild(this.head.render());
    this.element.appendChild(this.table.render());

    document.removeEventListener('click', this.handleDocumentClick);
    document.addEventListener('click', this.handleDocumentClick);
  }

  handleDocumentClick = (event: MouseEvent) => {
    let { target } = event;

    if (!target) {
      return;
    }

    target = (target as Element).closest(
      `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"][${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
    );

    if (!target) {
      return;
    }

    const action = (target as Element).getAttribute(
      ATTRIBUTE_ACTION
    ) as keyof this;

    const actionArgs = (target as Element).getAttribute(ATTRIBUTE_ACTION_ARGS);

    const actionFlush = (target as Element).getAttribute(
      ATTRIBUTE_ACTION_FLUSH
    );

    if (!actionFlush) {
      event.preventDefault();
    }

    if (!action || !this.supportedActions.includes(action as string)) {
      return;
    }

    const actionFunc = this[action] as any;

    if (actionFunc instanceof Function) {
      return actionFunc({
        args: actionArgs,
        event,
      });
    }
  };

  changeMonth = async ({ args, event }: { args?: unknown; event?: Event }) => {
    //! args - это месяц
    this.activeDate.setMonth(
      this.activeDate.getMonth() + parseInt(args as string)
    );

    if (typeof this.element.replaceChildren === 'function') {
      return this.element.replaceChildren(
        this.head.render(),
        this.table.render()
      );
    }

    this.element.innerHTML = '';
    this.element.appendChild(this.head.render());
    this.element.appendChild(this.table.render());
  };

  changeDate = async ({ args, event }: { args?: unknown; event?: Event }) => {
    //! args - это дата

    const parts = (args as string)
      .split('-')
      .map((part) => parseInt(part))
      .filter((part) => !isNaN(part));

    const [year, month, day] = parts;

    if (parts.length < 3) {
      this.savedDate = null;
    } else {
      this.savedDate = new Date(year, month - 1, day);
    }

    let dispatchedEvent = new CustomEvent(EVENT_CHANGE_DATE_AFTER, {
      bubbles: true,
      cancelable: false,
      detail: {
        date: this.savedDate ? new Date(this.savedDate) : null,
      },
    });

    this.element.dispatchEvent(dispatchedEvent);

    document
      .querySelectorAll(
        `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
          `${ATTRIBUTE_BASE_ID}`
        )}"][${ATTRIBUTE_INPUT}]`
      )
      .forEach((element) => {
        if (element.hasAttribute(ATTRIBUTE_INPUT_FORMATTED)) {
          if (this.savedDate) {
            element.setAttribute(
              'value',
              this.savedDate?.toLocaleString('ru', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              }) as string
            );
          } else {
            element.setAttribute('value', '');
          }
        } else {
          if (this.savedDate) {
            element.setAttribute('value', args as string);
          } else {
            element.setAttribute('value', '');
          }
        }

        const _event = new Event('input', {
          bubbles: true,
          cancelable: true,
        });

        element.dispatchEvent(_event);
      });
  };

  getPanel = ({ year, month }: { year: number; month: number }) => {
    const panelFlat = [];
    const panel = [];

    const matrixSize = 42;

    let leftBorder = new Date(year, month, 1).getDay();

    if (leftBorder === 0) {
      leftBorder = 7;
    }

    // -2 так как 0 день текущего месяца это последний день предыдущего месяца найс ))

    leftBorder = leftBorder - 2;

    for (let i = leftBorder * -1; i <= 0; i++) {
      const date = new Date(year, month, i);
      panelFlat.push(date);
    }

    const rightBorder = matrixSize - leftBorder;

    for (let i = 1; i < rightBorder; i++) {
      const date = new Date(year, month, i);
      panelFlat.push(date);
    }

    for (let i = 0; i < panelFlat.length; i += 7) {
      panel.push(panelFlat.slice(i, i + 7));
    }

    return panel;
  };
}

export {
  Calendar as inputCalendar,
  CalendarHead as inputCalendarHead,
  CalendarHeadConfigType as InputCalendarHeadConfigType,
  CalendarHeadPropsType as InputCalendarHeadPropsType,
  CalendarTable as inputCalendarTable,
  CalendarTableConfigType as InputCalendarTableConfigType,
  CalendarTablePropsType as InputCalendarTablePropsType,
  CalendarConfigType as InputCalendarConfigType,
  CalendarPropsType as InputCalendarPropsType,
};
