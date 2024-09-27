class Deque {
    constructor(size) {
        this.MAX = 100;
        this.arr = new Array(this.MAX);
        this.front = -1;
        this.rear = 0;
        this.size = size;
    }

    isFull() {
        return (this.front === 0 && this.rear === this.size - 1) || this.front === this.rear + 1;
    }

    isEmpty() {
        return this.front === -1;
    }

    insertfront(key) {
        if (this.isFull()) {
            console.log("Overflow");
            return;
        }
        if (this.front === -1) {
            this.front = 0;
            this.rear = 0;
        } else if (this.front === 0)
            this.front = this.size - 1;
        else
            this.front = this.front - 1;

        this.arr[this.front] = key;
    }

    insertrear(key) {
        if (this.isFull()) {
            console.log("Overflow");
            return;
        }
        if (this.front === -1) {
            this.front = 0;
            this.rear = 0;
        } else if (this.rear === this.size - 1)
            this.rear = 0;
        else
            this.rear = this.rear + 1;

        this.arr[this.rear] = key;
    }

    deletefront() {
        if (this.isEmpty()) {
            console.log("Queue Underflow");
            return;
        }
        if (this.front === this.rear) {
            this.front = -1;
            this.rear = -1;
        } else if (this.front === this.size - 1)
            this.front = 0;
        else
            this.front = this.front + 1;
    }

    deleterear() {
        if (this.isEmpty()) {
            console.log("Underflow");
            return;
        }
        if (this.front === this.rear) {
            this.front = -1;
            this.rear = -1;
        } else if (this.rear === 0)
            this.rear = this.size - 1;
        else
            this.rear = this.rear - 1;
    }

    getFront() {
        if (this.isEmpty()) {
            console.log("Underflow");
            return -1;
        }
        return this.arr[this.front];
    }

    getRear() {
        if (this.isEmpty() || this.rear < 0) {
            console.log("Underflow");
            return -1;
        }
        return this.arr[this.rear];
    }
}

// Example usage
const dq = new Deque(4);
console.log("Insert element at rear end: 12");
dq.insertrear(12);
console.log("Insert element at rear end: 14");
dq.insertrear(14);
console.log("get rear element : " + dq.getRear());
dq.deleterear();
console.log("After delete rear element new rear become : " + dq.getRear());
console.log("inserting element at front end");
dq.insertfront(13);
console.log("get front element: " + dq.getFront());
dq.deletefront();
console.log("After delete front element new front become : " + +dq.getFront());

