function getTouchPoint(event, changed = false) {
    const list = changed ? event?.changedTouches : event?.touches;
    return list?.[0] ?? null;
}

export function createHorizontalSwipeTracker(options = {}) {
    const cfg = {
        axisLockThreshold: Number(options.axisLockThreshold ?? 8),
        axisBias: Number(options.axisBias ?? 1.35),
        minDistance: Number(options.minDistance ?? 56),
        minHorizontalRatio: Number(options.minHorizontalRatio ?? 1.25),
    };

    const state = {
        startX: 0,
        startY: 0,
        dx: 0,
        dy: 0,
        axis: null, // 'x' | 'y' | null
        tracking: false,
    };

    return {
        start(event) {
            const p = getTouchPoint(event);
            if (!p) return false;
            state.startX = p.clientX;
            state.startY = p.clientY;
            state.dx = 0;
            state.dy = 0;
            state.axis = null;
            state.tracking = true;
            return true;
        },
        move(event) {
            if (!state.tracking) return { active: false, shouldPreventDefault: false, dx: 0, dy: 0, axis: state.axis };
            const p = getTouchPoint(event);
            if (!p) return { active: false, shouldPreventDefault: false, dx: state.dx, dy: state.dy, axis: state.axis };

            state.dx = p.clientX - state.startX;
            state.dy = p.clientY - state.startY;
            const absDx = Math.abs(state.dx);
            const absDy = Math.abs(state.dy);

            if (!state.axis && (absDx > cfg.axisLockThreshold || absDy > cfg.axisLockThreshold)) {
                state.axis = absDx > absDy * cfg.axisBias ? 'x' : 'y';
            }

            const horizontal = state.axis === 'x';
            return {
                active: horizontal,
                shouldPreventDefault: horizontal,
                dx: state.dx,
                dy: state.dy,
                axis: state.axis,
            };
        },
        end(event) {
            if (!state.tracking) {
                return { isSwipe: false, direction: null, dx: 0, dy: 0, axis: null };
            }
            const p = getTouchPoint(event, true) ?? getTouchPoint(event);
            if (p) {
                state.dx = p.clientX - state.startX;
                state.dy = p.clientY - state.startY;
            }

            const absDx = Math.abs(state.dx);
            const absDy = Math.abs(state.dy);
            const axis = state.axis;
            const isHorizontal = axis === 'x';
            const isSwipe = isHorizontal && absDx >= cfg.minDistance && absDx >= absDy * cfg.minHorizontalRatio;
            const direction = !isSwipe ? null : (state.dx < 0 ? 'left' : 'right');

            state.tracking = false;
            state.axis = null;

            return { isSwipe, direction, dx: state.dx, dy: state.dy, axis };
        },
        cancel() {
            state.tracking = false;
            state.axis = null;
            state.dx = 0;
            state.dy = 0;
        },
    };
}
